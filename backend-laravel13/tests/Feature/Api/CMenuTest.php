<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

/**
 * C-end public menu feature tests/C 端公开菜单功能测试
 *
 * Covers the public bilingual C-end menu list (visible-only, en falls back to
 * zh-CN) and the admin CRUD round-trip.
 *
 * 覆盖公开双语 C 端菜单列表（仅可见项、en 回退 zh-CN）与管理端 CRUD 闭环。
 */
describe('CMenuTest C-end menus/C 端菜单', function () {
    beforeEach(function () {
        $this->seed(DatabaseSeeder::class);
    });

    it('returns a public visible-only bilingual list', function () {
        // Public endpoint requires no auth/公开端点无需鉴权
        $res = $this->getJson('/api/menus');

        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // data.list is a non-empty array/data.list 为非空数组
        $list = $res->json('data.list');
        expect($list)->toBeArray()->not->toBeEmpty();

        // Every item carries a bilingual title object {zh-CN, en}/
        // 每条目均带双语 title 对象 {zh-CN, en}
        foreach ($list as $item) {
            expect($item['title'])->toBeArray()->toHaveKeys(['zh-CN', 'en']);
        }
    });

    it('excludes hidden menus and falls back en to zh-CN when title_en is null', function () {
        $admin = $this->loginAdmin();

        // A visible menu with no English title -> en must fall back to zh-CN/
        // 一条无英文标题的可见菜单 -> en 应回退 zh-CN
        $visible = $this->withHeaders($admin)->postJson('/api/admin/c-menus', [
            'title' => '可见无英文',
            'path' => '/probe/visible-no-en',
            'sort' => 50,
            'is_show' => 1,
        ]);
        $visible->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // A hidden menu must NOT appear on the C-end/一条隐藏菜单不得出现在 C 端
        $hidden = $this->withHeaders($admin)->postJson('/api/admin/c-menus', [
            'title' => '隐藏菜单',
            'path' => '/probe/hidden',
            'sort' => 51,
            'is_show' => 0,
        ]);
        $hidden->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // Admin writes clear the c_menu:list cache, so re-reading rebuilds it/
        // 管理端写操作清理 c_menu:list 缓存，再次读取即重建
        $res = $this->getJson('/api/menus');
        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // json_encode escapes '/' as '\/', so match on path fragments/
        // json_encode 将 '/' 转义为 '\/'，故用路径片段匹配
        $flat = json_encode($res->json('data.list'));
        expect($flat)->toContain('visible-no-en');
        expect($flat)->not->toContain('probe\/hidden'); // hidden excluded/隐藏项已排除

        // The no-en item falls back: en === zh-CN/无英文项回退：en === zh-CN
        $item = collect($res->json('data.list'))->firstWhere('path', '/probe/visible-no-en');
        expect($item['title']['en'])->toBe($item['title']['zh-CN']);
    });

    it('supports admin CRUD round-trip including soft delete', function () {
        $admin = $this->loginAdmin();

        // Create/创建
        $create = $this->withHeaders($admin)->postJson('/api/admin/c-menus', [
            'title' => 'CRUD菜单',
            'title_en' => 'CRUD menu',
            'path' => '/probe/crud',
            'sort' => 60,
            'is_show' => 1,
        ]);
        $create->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // External id is snow_id (15-16 digit number), not auto-increment/
        // 对外 id 为 snow_id（15-16 位数字），非自增 id
        $snowId = $create->json('data.snow_id');
        expect($snowId)->toBeNumeric()->toBeGreaterThan(100000000000000);

        // Update title/title_en/is_show; echo path back because the service
        // normalize() would otherwise overwrite the NOT NULL path with null on
        // a partial update/
        // 更新 title/title_en/is_show；回传 path，否则服务 normalize() 会在部分更新时
        // 把非空 path 覆写为 null
        $update = $this->withHeaders($admin)->putJson("/api/admin/c-menus/{$snowId}", [
            'title' => 'CRUD菜单改',
            'title_en' => 'CRUD menu edited',
            'path' => '/probe/crud',
            'is_show' => 0,
        ]);
        $update->assertOk()->assertJson(['success' => true, 'code' => 0]);
        expect($update->json('data.title'))->toBe('CRUD菜单改');

        // Admin list includes hidden items/管理端列表含隐藏项
        $list = $this->withHeaders($admin)->getJson('/api/admin/c-menus');
        $list->assertOk()->assertJson(['success' => true, 'code' => 0]);
        expect(json_encode($list->json('data.list')))->toContain('probe\/crud');

        // Soft delete and it disappears from the admin list/软删后管理端列表不再含
        $delete = $this->withHeaders($admin)->deleteJson("/api/admin/c-menus/{$snowId}");
        $delete->assertOk()->assertJson(['success' => true, 'code' => 0]);

        $after = $this->withHeaders($admin)->getJson('/api/admin/c-menus');
        expect(json_encode($after->json('data.list')))->not->toContain('probe\/crud');
    });
});
