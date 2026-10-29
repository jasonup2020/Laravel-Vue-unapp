<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Support\SaltHasher;
use App\Support\Snowflake;

/**
 * C端普通用户种子 / C-end User seeder
 * 写入 1 条演示用户，供 C 端登录测试
 * Seeds 1 demo user for C-end login testing
 */
class UserSeeder extends Seeder
{
    /**
     * 执行用户播种 / Seed demo user
     * 幂等：email 已存在则跳过 / Idempotent: skips existing email
     */
    public function run(): void
    {
        $email = 'user@example.com';
        $salt = SaltHasher::generate();
        $row = DB::table('users')->where('email', $email)->first();

        if (! $row) {
            $now = now();
            DB::table('users')->insert([
                'name' => '示例用户',
                'email' => $email,
                'password' => SaltHasher::make('password', $salt),
                'salt' => $salt,
                'status' => 1,
                'snow_id' => Snowflake::id(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            return;
        }

        // 已存在但缺盐（旧版 Hash(明文)）→ 补盐并重哈希，保证新加盐校验可通过
        if (empty($row->salt) || ! str_starts_with((string) $row->password, '$2')) {
            DB::table('users')->where('id', $row->id)->update([
                'salt' => $salt,
                'password' => SaltHasher::make('password', $salt),
                'updated_at' => now(),
            ]);
        }
    }
}
