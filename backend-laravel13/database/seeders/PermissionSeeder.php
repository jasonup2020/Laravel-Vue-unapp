<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Support\Snowflake;

/**
 * 权限种子 / Permission seeder
 * 写入全部 42 条权限码（40 条系统预置 type=1 + 2 条自定义 type=2），group 按资源模块划分
 * Inserts all 42 permission codes (40 system type=1 + 2 custom type=2), grouped by resource
 */
class PermissionSeeder extends Seeder
{
    /**
     * 执行权限播种 / Seed permissions
     * 幂等：已存在的 code 跳过 / Idempotent: skips existing codes
     * 每条权限含 action（list/create/update/delete 或自定义）与 type（1=系统 2=自定义）
     */
    public function run(): void
    {
        $permissions = [
            // common 公共权限模块（所有角色基础能力）/ Common permissions shared by all roles
            ['name' => '工作台查看', 'label_en' => 'Dashboard view', 'code' => 'admin:dashboard:view', 'group' => 'common', 'action' => 'view', 'type' => 1],
            ['name' => '个人资料查看', 'label_en' => 'Profile view', 'code' => 'admin:profile:view', 'group' => 'common', 'action' => 'view', 'type' => 1],
            ['name' => '个人资料修改', 'label_en' => 'Profile update', 'code' => 'admin:profile:update', 'group' => 'common', 'action' => 'update', 'type' => 1],
            ['name' => '修改密码', 'label_en' => 'Change password', 'code' => 'admin:profile:password', 'group' => 'common', 'action' => 'update', 'type' => 1],
            // product 商品模块 / Product module
            ['name' => '商品列表', 'label_en' => 'Product list', 'code' => 'admin:product:list', 'group' => 'product', 'action' => 'list', 'type' => 1],
            ['name' => '商品创建', 'label_en' => 'Product create', 'code' => 'admin:product:create', 'group' => 'product', 'action' => 'create', 'type' => 1],
            ['name' => '商品更新', 'label_en' => 'Product update', 'code' => 'admin:product:update', 'group' => 'product', 'action' => 'update', 'type' => 1],
            ['name' => '商品删除', 'label_en' => 'Product delete', 'code' => 'admin:product:delete', 'group' => 'product', 'action' => 'delete', 'type' => 1],
            ['name' => '商品导出', 'label_en' => 'Product export', 'code' => 'admin:product:export', 'group' => 'product', 'action' => 'export', 'type' => 2],
            ['name' => '商品审核', 'label_en' => 'Product audit', 'code' => 'admin:product:audit', 'group' => 'product', 'action' => 'audit', 'type' => 2],
            // dict 字典模块 / Dictionary module
            ['name' => '字典列表', 'label_en' => 'Dictionary list', 'code' => 'admin:dict:list', 'group' => 'dict', 'action' => 'list', 'type' => 1],
            ['name' => '字典创建', 'label_en' => 'Dictionary create', 'code' => 'admin:dict:create', 'group' => 'dict', 'action' => 'create', 'type' => 1],
            ['name' => '字典更新', 'label_en' => 'Dictionary update', 'code' => 'admin:dict:update', 'group' => 'dict', 'action' => 'update', 'type' => 1],
            ['name' => '字典删除', 'label_en' => 'Dictionary delete', 'code' => 'admin:dict:delete', 'group' => 'dict', 'action' => 'delete', 'type' => 1],
            // user 普通用户模块 / C-end user module
            ['name' => '普通用户列表', 'label_en' => 'User list', 'code' => 'admin:user:list', 'group' => 'user', 'action' => 'list', 'type' => 1],
            ['name' => '普通用户更新', 'label_en' => 'User update', 'code' => 'admin:user:update', 'group' => 'user', 'action' => 'update', 'type' => 1],
            ['name' => '普通用户踢下线', 'label_en' => 'User kick offline', 'code' => 'admin:user:kick', 'group' => 'user', 'action' => 'kick', 'type' => 1],
            // admin 管理员模块 / Admin management module
            ['name' => '管理员列表', 'label_en' => 'Admin list', 'code' => 'admin:admin:list', 'group' => 'admin', 'action' => 'list', 'type' => 1],
            ['name' => '管理员创建', 'label_en' => 'Admin create', 'code' => 'admin:admin:create', 'group' => 'admin', 'action' => 'create', 'type' => 1],
            ['name' => '管理员更新', 'label_en' => 'Admin update', 'code' => 'admin:admin:update', 'group' => 'admin', 'action' => 'update', 'type' => 1],
            ['name' => '管理员删除', 'label_en' => 'Admin delete', 'code' => 'admin:admin:delete', 'group' => 'admin', 'action' => 'delete', 'type' => 1],
            // role 角色模块 / Role module
            ['name' => '角色列表', 'label_en' => 'Role list', 'code' => 'admin:role:list', 'group' => 'role', 'action' => 'list', 'type' => 1],
            ['name' => '角色创建', 'label_en' => 'Role create', 'code' => 'admin:role:create', 'group' => 'role', 'action' => 'create', 'type' => 1],
            ['name' => '角色更新', 'label_en' => 'Role update', 'code' => 'admin:role:update', 'group' => 'role', 'action' => 'update', 'type' => 1],
            ['name' => '角色删除', 'label_en' => 'Role delete', 'code' => 'admin:role:delete', 'group' => 'role', 'action' => 'delete', 'type' => 1],
            ['name' => '角色分配权限', 'label_en' => 'Role assign permissions', 'code' => 'admin:role:assign', 'group' => 'role', 'action' => 'assign', 'type' => 1],
            // permission 权限模块 / Permission module
            ['name' => '权限列表', 'label_en' => 'Permission list', 'code' => 'admin:permission:list', 'group' => 'permission', 'action' => 'list', 'type' => 1],
            ['name' => '权限创建', 'label_en' => 'Permission create', 'code' => 'admin:permission:create', 'group' => 'permission', 'action' => 'create', 'type' => 1],
            ['name' => '权限更新', 'label_en' => 'Permission update', 'code' => 'admin:permission:update', 'group' => 'permission', 'action' => 'update', 'type' => 1],
            ['name' => '权限删除', 'label_en' => 'Permission delete', 'code' => 'admin:permission:delete', 'group' => 'permission', 'action' => 'delete', 'type' => 1],
            // menu 菜单模块 / Menu module
            ['name' => '菜单列表', 'label_en' => 'Menu list', 'code' => 'admin:menu:list', 'group' => 'menu', 'action' => 'list', 'type' => 1],
            ['name' => '菜单创建', 'label_en' => 'Menu create', 'code' => 'admin:menu:create', 'group' => 'menu', 'action' => 'create', 'type' => 1],
            ['name' => '菜单更新', 'label_en' => 'Menu update', 'code' => 'admin:menu:update', 'group' => 'menu', 'action' => 'update', 'type' => 1],
            ['name' => '菜单删除', 'label_en' => 'Menu delete', 'code' => 'admin:menu:delete', 'group' => 'menu', 'action' => 'delete', 'type' => 1],
            // message 消息模块 / Message module
            ['name' => '消息列表', 'label_en' => 'Message list', 'code' => 'admin:message:list', 'group' => 'message', 'action' => 'list', 'type' => 1],
            ['name' => '消息发送', 'label_en' => 'Message send', 'code' => 'admin:message:create', 'group' => 'message', 'action' => 'create', 'type' => 1],
            // favorite 收藏模块 / Favorite module
            ['name' => '收藏列表', 'label_en' => 'Favorite list', 'code' => 'admin:favorite:list', 'group' => 'favorite', 'action' => 'list', 'type' => 1],
            // recycle 回收站模块 / Recycle bin module
            ['name' => '回收站列表', 'label_en' => 'Recycle bin list', 'code' => 'admin:recycle:list', 'group' => 'recycle', 'action' => 'list', 'type' => 1],
            ['name' => '回收站恢复', 'label_en' => 'Recycle bin restore', 'code' => 'admin:recycle:restore', 'group' => 'recycle', 'action' => 'restore', 'type' => 1],
            // log 操作日志模块 / Operation log module
            ['name' => '操作日志列表', 'label_en' => 'Operation log list', 'code' => 'admin:log:list', 'group' => 'log', 'action' => 'list', 'type' => 1],
            // ip IP黑名单模块 / IP blacklist module
            ['name' => 'IP黑名单列表', 'label_en' => 'IP blacklist list', 'code' => 'admin:ip:list', 'group' => 'ip', 'action' => 'list', 'type' => 1],
            ['name' => 'IP黑名单创建', 'label_en' => 'IP blacklist create', 'code' => 'admin:ip:create', 'group' => 'ip', 'action' => 'create', 'type' => 1],
            ['name' => 'IP黑名单删除', 'label_en' => 'IP blacklist delete', 'code' => 'admin:ip:delete', 'group' => 'ip', 'action' => 'delete', 'type' => 1],
            // rate 限流规则模块 / Rate limit rule module
            ['name' => '限流规则列表', 'label_en' => 'Rate limit rule list', 'code' => 'admin:rate:list', 'group' => 'rate', 'action' => 'list', 'type' => 1],
            ['name' => '新增限流规则', 'label_en' => 'Rate limit rule create', 'code' => 'admin:rate:create', 'group' => 'rate', 'action' => 'create', 'type' => 1],
            ['name' => '修改限流规则', 'label_en' => 'Rate limit rule update', 'code' => 'admin:rate:update', 'group' => 'rate', 'action' => 'update', 'type' => 1],
            ['name' => '删除限流规则', 'label_en' => 'Rate limit rule delete', 'code' => 'admin:rate:delete', 'group' => 'rate', 'action' => 'delete', 'type' => 1],
            // i18n 多语言模块 / Locale i18n module
            ['name' => '多语言列表', 'label_en' => 'Locale list', 'code' => 'admin:i18n:list', 'group' => 'system', 'action' => 'list', 'type' => 1],
            ['name' => '多语言更新', 'label_en' => 'Locale update', 'code' => 'admin:i18n:update', 'group' => 'system', 'action' => 'update', 'type' => 1],
            ['name' => '多语言刷新缓存', 'label_en' => 'Locale refresh cache', 'code' => 'admin:i18n:refresh', 'group' => 'system', 'action' => 'refresh', 'type' => 1],
        ];

        $now = now();
        // 清理已废弃的 c-menu 权限（C端菜单不做权限控制）/ Clean up deprecated c-menu permissions
        DB::table('permissions')->where('code', 'like', 'admin:c-menu:%')->delete();
        // 逐条 upsert：按 code 查找，存在则更新 name/label_en/group/action/type，不存在则插入
        // Upsert one by one: find by code, update fields if exists, insert if not
        foreach ($permissions as $p) {
            $existing = DB::table('permissions')->where('code', $p['code'])->first();
            if ($existing) {
                DB::table('permissions')->where('id', $existing->id)->update([
                    'name' => $p['name'],
                    'label_en' => $p['label_en'],
                    'group' => $p['group'],
                    'action' => $p['action'],
                    'type' => $p['type'],
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('permissions')->insert(array_merge($p, [
                    'snow_id' => Snowflake::id(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }
}
