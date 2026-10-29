<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Cache;

/**
 * Captcha service (GD image)/图形验证码服务（GD 绘制 PNG）.
 *
 * Generates a 4-char random code (excluding confusable 0/O/1/I), draws a PNG with
 * noise dots + interference lines + randomly placed/colored chars, stores the code
 * in Cache (one-time, TTL 300s).
 * 生成 4 位随机码（去除易混淆 0/O/1/I），GD 绘制带噪点/干扰线/随机位置颜色的 PNG，
 * 验证码存 Cache（一次性，TTL 300 秒）。
 */
class CaptchaService
{
    /** Code length/验证码位数 */
    private const CODE_LENGTH = 4;

    /** TTL in seconds/有效期（秒） */
    private const TTL = 300;

    /** Allowed chars (no 0/O/1/I)/允许字符（去除易混淆） */
    private const CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** TrueType font path (freetype available on this host)/TrueType 字体路径（本机 freetype 可用） */
    private const FONT = 'C:\\Windows\\Fonts\\arial.ttf';

    /**
     * Build cache key/构造缓存 Key.
     */
    private function key(string $id): string
    {
        return "captcha:{$id}";
    }

    /**
     * Generate a captcha: returns {captcha_id, image(data URL)}/
     * 生成验证码：返回 {captcha_id, image(data URL)}.
     *
     * @return array{captcha_id: string, image: string}
     */
    public function generate(): array
    {
        // Random id + random code/随机 id + 随机码
        $id = bin2hex(random_bytes(16));
        $code = '';
        $max = strlen(self::CHARS) - 1;
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::CHARS[random_int(0, $max)];
        }

        // Store plaintext code (one-time)/存明文码（一次性）
        Cache::put($this->key($id), $code, self::TTL);

        // Render PNG and wrap as data URL/渲染 PNG 并包成 data URL
        $png = $this->renderPng($code);

        return [
            'captcha_id' => $id,
            'image' => 'data:image/png;base64,'.base64_encode($png),
        ];
    }

    /**
     * Draw PNG captcha with GD/用 GD 绘制 PNG 验证码.
     */
    private function renderPng(string $code): string
    {
        $w = 150;
        $h = 50;

        // Create canvas with white bg/建画布白底
        $img = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $bg);

        // Noise dots/噪点
        for ($i = 0; $i < 60; $i++) {
            $c = imagecolorallocate($img, random_int(180, 230), random_int(180, 230), random_int(180, 230));
            imagesetpixel($img, random_int(0, $w), random_int(0, $h), $c);
        }

        // 3-5 interference lines/3-5 条干扰线
        $lines = random_int(3, 5);
        for ($i = 0; $i < $lines; $i++) {
            $c = imagecolorallocate($img, random_int(100, 200), random_int(100, 200), random_int(100, 200));
            imageline($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $c);
        }

        // Draw each char: prefer TrueType (random rotation/position/color), fallback to built-in font/
        // 逐字符绘制：优先 TrueType（随机旋转/位置/颜色），缺字体退内置字
        $useTtf = is_file(self::FONT);
        $len = strlen($code);
        for ($i = 0; $i < $len; $i++) {
            $color = imagecolorallocate($img, random_int(20, 120), random_int(20, 120), random_int(20, 120));
            $x = 12 + $i * 32 + random_int(-3, 3);
            if ($useTtf) {
                imagettftext($img, 22, random_int(-15, 15), $x, random_int(34, 42), $color, self::FONT, $code[$i]);
            } else {
                imagestring($img, 5, $x, 16, $code[$i], $color);
            }
        }

        // Capture PNG bytes/输出 PNG 二进制
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return $png;
    }

    /**
     * Validate captcha (one-time): consume the stored code on every attempt/
     * 校验验证码（一次性）：每次尝试都消费存储的码.
     *
     * @throws ApiException when missing/expired/consumed or mismatch/不存在、过期、已消费或不匹配时抛错
     */
    public function validate(string $id, string $code): void
    {
        // Empty input → required/空入参 → 必填
        if ($id === '' || $code === '') {
            throw new ApiException(ErrorCode::CAPTCHA_INVALID, 'api.captcha.required');
        }

        $key = $this->key($id);
        $stored = Cache::get($key);

        // Not found / expired / already consumed/不存在 / 过期 / 已消费
        if ($stored === null) {
            throw new ApiException(ErrorCode::CAPTCHA_INVALID, 'api.captcha.expired');
        }

        // One-time: forget regardless of result/一次性：无论对错都删除
        Cache::forget($key);

        // Case-insensitive comparison/大小写不敏感比对
        if (strcasecmp((string) $stored, $code) !== 0) {
            throw new ApiException(ErrorCode::CAPTCHA_INVALID, 'api.captcha.invalid');
        }
    }
}
