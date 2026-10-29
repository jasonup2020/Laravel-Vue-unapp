<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Support\Snowflake;

/**
 * 角色种子 / Role seeder
 * 创建 super-admin（全部权限）和 operator（商品/字典权限）
 * Creates super-admin (all permissions) and operator (product/dict permissions)
 */
class RoleSeeder extends Seeder
{
    /**
     * 执行角色播种 / Seed roles and permission bindings
     * 幂等：角色和绑定关系已存在则跳过 / Idempotent: skips existing
     */
    public function run(): void
    {
        $now = now();

        // 创建或获取 super-admin 角色 / Create or get super-admin role
        $superId = DB::table('roles')->where('code', 'super-admin')->value('id');
        if (! $superId) {
            $superId = DB::table('roles')->insertGetId([
                'name' => '超级管理员',
                'code' => 'super-admin',
                'remark' => '拥有全部权限',
                'snow_id' => Snowflake::id(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 创建或获取 operator 角色 / Create or get operator role
        $operatorId = DB::table('roles')->where('code', 'operator')->value('id');
        if (! $operatorId) {
            $operatorId = DB::table('roles')->insertGetId([
                'name' => '运营专员',
                'code' => 'operator',
                'remark' => '商品与字典管理权限',
                'snow_id' => Snowflake::id(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 绑定全部权限给 super-admin / Bind all permissions to super-admin
        $allPermIds = DB::table('permissions')->pluck('id')->all();
        foreach ($allPermIds as $pid) {
            $exists = DB::table('role_permission')
                ->where('role_id', $superId)
                ->where('permission_id', $pid)
                ->exists();
            if (! $exists) {
                DB::table('role_permission')->insert([
                    'role_id' => $superId,
                    'permission_id' => $pid,
                ]);
            }
        }

        // 绑定商品+字典权限给 operator / Bind product+dict permissions to operator
        $operatorPermIds = DB::table('permissions')
            ->whereIn('code', [
                'admin:product:list',
                'admin:product:create',
                'admin:product:update',
                'admin:product:delete',
                'admin:dict:list',
                'admin:dict:create',
                'admin:dict:update',
                'admin:dict:delete',
            ])
            ->pluck('id')->all();
        foreach ($operatorPermIds as $pid) {
            $exists = DB::table('role_permission')
                ->where('role_id', $operatorId)
                ->where('permission_id', $pid)
                ->exists();
            if (! $exists) {
                DB::table('role_permission')->insert([
                    'role_id' => $operatorId,
                    'permission_id' => $pid,
                ]);
            }
        }
    }
}
