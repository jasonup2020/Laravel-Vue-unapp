<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Support\Snowflake;

/**
 * C端菜单种子 / C-end menu seeder
 * 写入 5 条默认 C 端入口菜单，幂等 upsert（按 path 去重）
 * Seeds 5 default C-end menu entries, idempotent upsert by path
 */
class CMenuSeeder extends Seeder
{
    /**
     * 执行 C 端菜单播种 / Seed C-end menus
     * 幂等：按 path upsert，已存在则更新，不存在则插入
     * 清理旧版无前导斜杠的 path 行 / Clean up old paths without leading slash
     */
    public function run(): void
    {
        $now = now();

        // 清理旧版无前导斜杠的 path（软删除）/ Soft-delete old paths without leading slash
        $oldPaths = [
            'pages/product/list',
            'pages/dict/demo',
            'pages/favorite/index',
            'pages/message/index',
        ];
        DB::table('c_menus')->whereIn('path', $oldPaths)->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);

        $menus = [
            // 商品列表 / Product list
            ['title' => '商品列表', 'title_en' => 'Product list', 'icon' => '🛍️', 'path' => '/pages/product/list', 'url' => null, 'sort' => 1, 'is_show' => 1],
            // 字典示例 / Dictionary
            ['title' => '字典示例', 'title_en' => 'Dictionary', 'icon' => '📖', 'path' => '/pages/dict/index', 'url' => null, 'sort' => 2, 'is_show' => 1],
            // 我的收藏 / My favorites
            ['title' => '我的收藏', 'title_en' => 'My favorites', 'icon' => '⭐', 'path' => '/pages/favorite/index', 'url' => null, 'sort' => 3, 'is_show' => 1],
            // 消息中心 / Message center
            ['title' => '消息中心', 'title_en' => 'Message center', 'icon' => '📬', 'path' => '/pages/message/index', 'url' => null, 'sort' => 4, 'is_show' => 1],
            // 个人中心 / Mine
            ['title' => '个人中心', 'title_en' => 'Mine', 'icon' => '👤', 'path' => '/pages/mine/index', 'url' => null, 'sort' => 5, 'is_show' => 1],
        ];

        foreach ($menus as $m) {
            // 幂等：按 path upsert / Idempotent: upsert by path
            $existing = DB::table('c_menus')->where('path', $m['path'])->whereNull('deleted_at')->first();
            if ($existing) {
                DB::table('c_menus')->where('id', $existing->id)->update(array_merge($m, [
                    'updated_at' => $now,
                ]));
            } else {
                DB::table('c_menus')->insert(array_merge($m, [
                    'parent_id' => 0,
                    'snow_id' => Snowflake::id(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }
}
