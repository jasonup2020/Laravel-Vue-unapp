<?php

declare(strict_types=1);

namespace App\Models\Casts;

use App\Support\SaltHasher;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * 加盐密码自定义 cast / Salted password cast.
 *
 * 赋值时读取行内 salt 列，将 password 存为 Hash(明文 + salt)；
 * 已是 bcrypt 的值原样返回（避免二次哈希）。读取时原样返回哈希串。
 */
class SaltedPasswordCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): mixed
    {
        return $value;
    }

    public function set($model, string $key, $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }
        // 已是 bcrypt 哈希则不重复加盐哈希（供 Seeder/Factory 传已哈希值兜底）
        if (is_string($value) && SaltHasher::isHashed($value)) {
            return $value;
        }

        $salt = (string) ($attributes['salt'] ?? $model->salt ?? '');

        return SaltHasher::make((string) $value, $salt);
    }
}
