<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * 数据库种子入口 / Database seeder entry point
 * 按依赖顺序依次执行：权限→角色→管理员→菜单→演示数据
 * Execution order: permissions → roles → admins → menus → demo data
 */
class DatabaseSeeder extends Seeder
{
    /**
     * 运行种子 / Run the seeders
     * 顺序保证：权限和角色先就位，管理员才能绑定角色，菜单再绑定到角色，演示数据随后，限流规则最后
     * Order ensures: permissions/roles ready before admin binding, then menu binding, demo data, then rate limit rules
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            AdminSeeder::class,
            MenuSeeder::class,
            DemoSeeder::class,
            UserSeeder::class,
            CMenuSeeder::class,
            RateLimitRuleSeeder::class,
        ]);
    }
}
