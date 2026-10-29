<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Hash;

/**
 * 加盐密码哈希 / Salted password hasher.
 *
 * 密码存储规则：password = Hash(明文 + 盐)，盐为每位用户独立的 6 位随机字母数字。
 * 所有写入（创建/改密/重置/种子）与校验（登录/改密）均经此类，避免散落 Hash::make/check。
 */
class SaltHasher
{
    /** 盐字符集：字母 + 数字，剔除易混淆字符（0/O、1/I/l） */
    private const CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

    /**
     * 生成 n 位随机盐（字母+数字）/ Generate a random n-char salt (letters + digits).
     */
    public static function generate(int $length = 6): string
    {
        $salt = '';
        $max = strlen(self::CHARS) - 1;
        for ($i = 0; $i < $length; $i++) {
            $salt .= self::CHARS[random_int(0, $max)];
        }

        return $salt;
    }

    /**
     * 计算 Hash(明文 + 盐) / Hash(plaintext + salt).
     */
    public static function make(string $plain, ?string $salt = null): string
    {
        return Hash::make($plain . ($salt ?? ''));
    }

    /**
     * 校验 明文 + 盐 是否与存储哈希匹配 / Verify plaintext + salt against stored hash.
     */
    public static function check(string $plain, ?string $salt, string $hash): bool
    {
        return Hash::check($plain . (string) $salt, $hash);
    }

    /**
     * 判断字符串是否为已哈希的 bcrypt 值（避免二次哈希）/ Whether a value is already bcrypt-hashed.
     */
    public static function isHashed(string $value): bool
    {
        return preg_match('/^\$2[aby]\$/i', $value) === 1;
    }
}
