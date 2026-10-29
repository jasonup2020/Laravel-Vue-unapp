<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Support\SaltHasher;
use App\Support\Snowflake;

/**
 * 管理员种子 / Admin seeder
 * 创建超级管理员 admin/admin123（绑定 super-admin）和演示管理员 demo/demo123（绑定 operator）
 * Creates super admin admin/admin123 (bound to super-admin) and demo admin demo/demo123 (bound to operator)
 */
class AdminSeeder extends Seeder
{
    /**
     * 执行管理员播种 / Seed admins and role bindings
     * 幂等：username 已存在则跳过 / Idempotent: skips existing usernames
     */
    public function run(): void
    {
        $now = now();

        // 创建超级管理员 admin / Create super admin
        $adminRow = DB::table('admins')->where('username', 'admin')->first();
        if (! $adminRow) {
            $adminSalt = SaltHasher::generate();
            $adminId = DB::table('admins')->insertGetId([
                'username' => 'admin',
                'nickname' => '超级管理员',
                'email' => 'admin@example.com',
                'password' => SaltHasher::make('admin123', $adminSalt),
                'salt' => $adminSalt,
                'avatar' => null,
                'status' => 1,
                'snow_id' => Snowflake::id(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $adminId = (int) $adminRow->id;
            // 已存在但缺盐（旧版 Hash(明文)）→ 补盐并重哈希，保证新加盐校验可通过
            if (empty($adminRow->salt) || ! str_starts_with((string) $adminRow->password, '$2')) {
                $adminSalt = SaltHasher::generate();
                DB::table('admins')->where('id', $adminId)->update([
                    'salt' => $adminSalt,
                    'password' => SaltHasher::make('admin123', $adminSalt),
                    'updated_at' => $now,
                ]);
            }
        }

        // 创建演示管理员 demo / Create demo admin
        $demoRow = DB::table('admins')->where('username', 'demo')->first();
        if (! $demoRow) {
            $demoSalt = SaltHasher::generate();
            $demoId = DB::table('admins')->insertGetId([
                'username' => 'demo',
                'nickname' => '演示管理员',
                'email' => 'demo@example.com',
                'password' => SaltHasher::make('demo123', $demoSalt),
                'salt' => $demoSalt,
                'avatar' => null,
                'status' => 1,
                'snow_id' => Snowflake::id(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $demoId = (int) $demoRow->id;
            // 已存在但缺盐（旧版 Hash(明文)）→ 补盐并重哈希
            if (empty($demoRow->salt) || ! str_starts_with((string) $demoRow->password, '$2')) {
                $demoSalt = SaltHasher::generate();
                DB::table('admins')->where('id', $demoId)->update([
                    'salt' => $demoSalt,
                    'password' => SaltHasher::make('demo123', $demoSalt),
                    'updated_at' => $now,
                ]);
            }
        }

        // 绑定 super-admin 角色给 admin / Bind super-admin role to admin
        $superRoleId = DB::table('roles')->where('code', 'super-admin')->value('id');
        if ($superRoleId) {
            $exists = DB::table('admin_role')
                ->where('admin_id', $adminId)
                ->where('role_id', $superRoleId)
                ->exists();
            if (! $exists) {
                DB::table('admin_role')->insert([
                    'admin_id' => $adminId,
                    'role_id' => $superRoleId,
                ]);
            }
        }

        // 绑定 operator 角色给 demo / Bind operator role to demo
        $operatorRoleId = DB::table('roles')->where('code', 'operator')->value('id');
        if ($operatorRoleId) {
            $exists = DB::table('admin_role')
                ->where('admin_id', $demoId)
                ->where('role_id', $operatorRoleId)
                ->exists();
            if (! $exists) {
                DB::table('admin_role')->insert([
                    'admin_id' => $demoId,
                    'role_id' => $operatorRoleId,
                ]);
            }
        }
    }
}
