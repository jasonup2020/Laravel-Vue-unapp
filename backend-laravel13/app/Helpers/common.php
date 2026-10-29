<?php

declare(strict_types=1);

use App\Support\Snowflake;
use Illuminate\Http\Request;

if (!function_exists('snow_id')) {
    /**
     * Generate the outward-facing snowflake business ID (positive 64-bit int)/生成对外暴露的雪花业务 ID（64 位正整数）
     */
    function snow_id(): int
    {
        return Snowflake::id();
    }
}

if (!function_exists('client_ip')) {
    /**
     * Get client IP of current request (real IP when trusted proxy is configured)/取当前请求的客户端 IP（框架已配置 trusted proxy 时返回真实 IP）
     */
    function client_ip(): string
    {
        $ip = request()->ip();

        return $ip === null ? '' : (string) $ip;
    }
}

if (!function_exists('client_fingerprint')) {
    /**
     * Read browser fingerprint from X-Client-Fingerprint header; default 'unknown'/取浏览器指纹（X-Client-Fingerprint 请求头），缺省 'unknown'
     *
     * Note: fingerprint is only a rate-limit aid and can be tampered with; never use it for auth./
     * 注意：指纹仅用于限流辅助，可被篡改，禁止用于鉴权
     */
    function client_fingerprint(?Request $request = null): string
    {
        $request = $request ?? request();
        $fp = $request->header('X-Client-Fingerprint');

        return is_string($fp) && $fp !== '' ? $fp : 'unknown';
    }
}

if (!function_exists('mask_text')) {
    /**
     * Mask a string: keep first $keepHead and last $keepTail chars, replace middle with '*'/
     * 文本脱敏：保留前 keepHead 个字符、后 keepTail 个字符，中间替换为 *
     *
     * Returns original string when too short; counts UTF-8 chars./
     * 长度不足时原样返回。按 UTF-8 字符计数
     */
    function mask_text(string $s, int $keepHead, int $keepTail): string
    {
        if ($s === '') {
            return $s;
        }

        $len = mb_strlen($s, 'UTF-8');

        if ($keepHead < 0) {
            $keepHead = 0;
        }
        if ($keepTail < 0) {
            $keepTail = 0;
        }

        if ($len <= $keepHead + $keepTail) {
            return $s;
        }

        $head = mb_substr($s, 0, $keepHead, 'UTF-8');
        $tail = $keepTail > 0 ? mb_substr($s, -$keepTail, null, 'UTF-8') : '';
        $maskLen = $len - $keepHead - $keepTail;

        return $head . str_repeat('*', $maskLen) . $tail;
    }
}
