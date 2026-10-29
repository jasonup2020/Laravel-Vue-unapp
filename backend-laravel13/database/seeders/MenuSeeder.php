<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Support\Snowflake;

/**
 * 菜单种子 / Menu seeder
 * 创建后台菜单树并绑定角色菜单关系
 * Creates admin menu tree and binds role-menu relationships
 * super-admin 绑定全部菜单，operator 绑定工作台+商品+字典菜单
 */
class MenuSeeder extends Seeder
{
    /**
     * 执行菜单播种 / Seed menu tree and role-menu bindings
     * 开发阶段 truncate 后重建，可重复执行 / Truncates and rebuilds for dev, re-runnable
     */
    public function run(): void
    {
        $now = now();

        // 清空已有菜单数据（开发可重复）/ Clear existing menus (re-runnable in dev)
        DB::table('admin_menus')->truncate();
        DB::table('role_menu')->truncate();

        // 1. 工作台 / Dashboard
        $dashboardId = DB::table('admin_menus')->insertGetId([
            'parent_id' => 0,
            'title' => '工作台',
            'title_en' => 'Dashboard',
            'path' => '/dashboard',
            'component' => 'dashboard/index',
            'icon' => 'dashboard',
            'permission_code' => null,
            'sort' => 0,
            'is_show' => 1,
            'is_cache' => 1,
            'is_external' => 0,
            'external_url' => null,
            'snow_id' => Snowflake::id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 2. 用户管理 / User management
        DB::table('admin_menus')->insert([
            'parent_id' => 0,
            'title' => '用户管理',
            'title_en' => 'Users',
            'path' => '/user',
            'component' => 'user/index',
            'icon' => 'user',
            'permission_code' => 'admin:user:list',
            'sort' => 1,
            'is_show' => 1,
            'is_cache' => 1,
            'is_external' => 0,
            'external_url' => null,
            'snow_id' => Snowflake::id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 3. 业务管理（目录）/ Business management directory
        // NOTE: parent_id stores the parent's snow_id (consistent with UI-created rows)
        $bizSnowId = Snowflake::id();
        $bizParentId = DB::table('admin_menus')->insertGetId([
            'parent_id' => 0,
            'title' => '业务管理',
            'title_en' => 'Business',
            'path' => null,
            'component' => null,
            'icon' => 'business',
            'permission_code' => null,
            'sort' => 2,
            'is_show' => 1,
            'is_cache' => 0,
            'is_external' => 0,
            'external_url' => null,
            'snow_id' => $bizSnowId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 3.1 商品管理 / Product management
        $productMenuId = DB::table('admin_menus')->insertGetId([
            'parent_id' => $bizSnowId,
            'title' => '商品管理',
            'title_en' => 'Products',
            'path' => '/product',
            'component' => 'product/index',
            'icon' => 'product',
            'permission_code' => 'admin:product:list',
            'sort' => 0,
            'is_show' => 1,
            'is_cache' => 1,
            'is_external' => 0,
            'external_url' => null,
            'snow_id' => Snowflake::id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 3.2 字典管理 / Dictionary management
        $dictMenuId = DB::table('admin_menus')->insertGetId([
            'parent_id' => $bizSnowId,
            'title' => '字典管理',
            'title_en' => 'Dictionary',
            'path' => '/dict',
            'component' => 'dict/index',
            'icon' => 'dict',
            'permission_code' => 'admin:dict:list',
            'sort' => 1,
            'is_show' => 1,
            'is_cache' => 1,
            'is_external' => 0,
            'external_url' => null,
            'snow_id' => Snowflake::id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 4. 系统管理（目录）/ System management directory
        $sysSnowId = Snowflake::id();
        $sysParentId = DB::table('admin_menus')->insertGetId([
            'parent_id' => 0,
            'title' => '系统管理',
            'title_en' => 'System',
            'path' => null,
            'component' => null,
            'icon' => 'system',
            'permission_code' => null,
            'sort' => 3,
            'is_show' => 1,
            'is_cache' => 0,
            'is_external' => 0,
            'external_url' => null,
            'snow_id' => $sysSnowId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        // 4.1 管理员管理 / Admin management
        DB::table('admin_menus')->insert([
            'parent_id' => $sysSnowId,
            'title' => '管理员管理',
            'title_en' => 'Admins',
            'path' => '/admin',
            'component' => 'admin/index',
            'icon' => 'admin',
            'permission_code' => 'admin:admin:list',
            'sort' => 0,
            'is_show' => 1,
            'is_cache' => 1,
            'is_external' => 0,
            'external_url' => null,
            'snow_id' => Snowflake::id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        // 4.2 角色管理 / Role management
        DB::table('admin_menus')->insert([
            'parent_id' => $sysSnowId,
            'title' => '角色管理',
            'title_en' => 'Roles',
            'path' => '/role',
            'component' => 'role/index',
            'icon' => 'role',
            'permission_code' => 'admin:role:list',
            'sort' => 1,
            'is_show' => 1,
            'is_cache' => 1,
            'is_external' => 0,
            'external_url' => null,
            'snow_id' => Snowflake::id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        // 4.3 菜单管理 / Menu management
        DB::table('admin_menus')->insert([
            'parent_id' => $sysSnowId,
            'title' => '菜单管理',
            'title_en' => 'Menus',
            'path' => '/menu',
            'component' => 'menu/index',
            'icon' => 'menu',
            'permission_code' => 'admin:menu:list',
            'sort' => 2,
            'is_show' => 1,
            'is_cache' => 1,
            'is_external' => 0,
            'external_url' => null,
            'snow_id' => Snowflake::id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        // 4.4 权限管理 / Permission management
        DB::table('admin_menus')->insert([
            'parent_id' => $sysSnowId,
            'title' => '权限管理',
            'title_en' => 'Permissions',
            'path' => '/permission',
            'component' => 'permission/index',
            'icon' => 'permission',
            'permission_code' => 'admin:permission:list',
            'sort' => 3,
            'is_show' => 1,
            'is_cache' => 1,
            'is_external' => 0,
            'external_url' => null,
            'snow_id' => Snowflake::id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 5. 消息中心 / Message center
        DB::table('admin_menus')->insert([
            'parent_id' => 0,
            'title' => '消息中心',
            'title_en' => 'Messages',
            'path' => '/message',
            'component' => 'message/index',
            'icon' => 'message',
            'permission_code' => 'admin:message:list',
            'sort' => 4,
            'is_show' => 1,
            'is_cache' => 1,
            'is_external' => 0,
            'external_url' => null,
            'snow_id' => Snowflake::id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 6. 审计管理（目录）/ Audit management directory
        $auditSnowId = Snowflake::id();
        $auditParentId = DB::table('admin_menus')->insertGetId([
            'parent_id' => 0,
            'title' => '审计管理',
            'title_en' => 'Audit',
            'path' => null,
            'component' => null,
            'icon' => 'audit',
            'permission_code' => null,
            'sort' => 5,
            'is_show' => 1,
            'is_cache' => 0,
            'is_external' => 0,
            'external_url' => null,
            'snow_id' => $auditSnowId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        // 6.1 操作日志 / Operation logs
        DB::table('admin_menus')->insert([
            'parent_id' => $auditSnowId,
            'title' => '操作日志',
            'title_en' => 'Operation Logs',
            'path' => '/operation-log',
            'component' => 'operation-log/index',
            'icon' => 'log',
            'permission_code' => 'admin:log:list',
            'sort' => 0,
            'is_show' => 1,
            'is_cache' => 1,
            'is_external' => 0,
            'external_url' => null,
            'snow_id' => Snowflake::id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        // 6.2 IP黑名单 / IP blacklist
        DB::table('admin_menus')->insert([
            'parent_id' => $auditSnowId,
            'title' => 'IP黑名单',
            'title_en' => 'IP Blacklist',
            'path' => '/ip-blacklist',
            'component' => 'ip-blacklist/index',
            'icon' => 'ip',
            'permission_code' => 'admin:ip:list',
            'sort' => 1,
            'is_show' => 1,
            'is_cache' => 1,
            'is_external' => 0,
            'external_url' => null,
            'snow_id' => Snowflake::id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        // 6.3 限流规则 / Rate limit rules
        DB::table('admin_menus')->insert([
            'parent_id' => $auditSnowId,
            'title' => '限流规则',
            'title_en' => 'Rate Limit',
            'path' => '/rate-limit',
            'component' => 'rate-limit/index',
            'icon' => 'rate',
            'permission_code' => 'admin:rate:list',
            'sort' => 2,
            'is_show' => 1,
            'is_cache' => 1,
            'is_external' => 0,
            'external_url' => null,
            'snow_id' => Snowflake::id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 7. 收藏管理 / Favorite management
        DB::table('admin_menus')->insert([
            'parent_id' => 0,
            'title' => '收藏管理',
            'title_en' => 'Favorites',
            'path' => '/favorite',
            'component' => 'favorite/index',
            'icon' => 'favorite',
            'permission_code' => 'admin:favorite:list',
            'sort' => 6,
            'is_show' => 1,
            'is_cache' => 1,
            'is_external' => 0,
            'external_url' => null,
            'snow_id' => Snowflake::id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 8. 回收站 / Recycle bin
        DB::table('admin_menus')->insert([
            'parent_id' => 0,
            'title' => '回收站',
            'title_en' => 'Recycle Bin',
            'path' => '/recycle-bin',
            'component' => 'recycle-bin/index',
            'icon' => 'recycle',
            'permission_code' => 'admin:recycle:list',
            'sort' => 7,
            'is_show' => 1,
            'is_cache' => 1,
            'is_external' => 0,
            'external_url' => null,
            'snow_id' => Snowflake::id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 9. C端菜单管理 / C-end menu management
        DB::table('admin_menus')->insert([
            'parent_id' => 0,
            'title' => 'C端菜单管理',
            'title_en' => 'C-menu Management',
            'path' => '/c-menu',
            'component' => 'c-menu/index',
            'icon' => 'c-menu',
            'permission_code' => null,
            'sort' => 8,
            'is_show' => 1,
            'is_cache' => 1,
            'is_external' => 0,
            'external_url' => null,
            'snow_id' => Snowflake::id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 角色菜单绑定 / Role-menu bindings
        $superRoleId = DB::table('roles')->where('code', 'super-admin')->value('id');
        $operatorRoleId = DB::table('roles')->where('code', 'operator')->value('id');

        // super-admin 绑定全部菜单 / Bind all menus to super-admin
        $allMenuIds = DB::table('admin_menus')->pluck('id')->all();
        if ($superRoleId) {
            foreach ($allMenuIds as $mid) {
                DB::table('role_menu')->insert([
                    'role_id' => $superRoleId,
                    'menu_id' => $mid,
                ]);
            }
        }

        // operator 绑定：工作台、业务管理目录、商品管理、字典管理 / Bind selected menus to operator
        if ($operatorRoleId) {
            $operatorMenuIds = [$dashboardId, $bizParentId, $productMenuId, $dictMenuId];
            foreach ($operatorMenuIds as $mid) {
                DB::table('role_menu')->insert([
                    'role_id' => $operatorRoleId,
                    'menu_id' => $mid,
                ]);
            }
        }
    }
}
