<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

/**
 * Locale pack & admin i18n management tests/语言包接口与后台多语言管理测试
 *
 * Covers/覆盖：
 * - C-end public GET /api/locale/{lang} returns flattened pack/
 *   C 端公开 GET /api/locale/{lang} 返回扁平包
 * - md5 fingerprint stability/md5 指纹稳定性
 * - meta endpoint returns only md5/meta 端点仅返回 md5
 * - Illegal lang -> body code 1004/非法语言 -> 体 code 1004
 * - Admin endpoints require auth (code 1002) / admin endpoints 需鉴权
 * - Super admin refresh succeeds (code 0)/超管刷新成功
 */
describe('LocaleTest/语言包接口', function () {
    beforeEach(function () {
        // Seed admin + permissions so loginAdmin() works/
        // 种子管理员与权限，供 loginAdmin() 使用
        $this->seed(DatabaseSeeder::class);
    });

    it('returns zh-CN pack with api.success and md5', function () {
        $res = $this->getJson('/api/locale/zh-CN');

        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);
        expect($res->json('data.lang'))->toBe('zh-CN');
        expect($res->json('data.md5'))->toMatch('/^[a-f0-9]{32}$/');
        // items keys contain literal dots (e.g. "api.success"), so bracket access./
        // items 的 key 自带点号（如 "api.success"），需用数组下标读取。
        $items = $res->json('data.items');
        expect($items['api.success'])->toBe('操作成功');
        expect($items)->toHaveKey('api.login.success');
    });

    it('returns en pack with api.success and md5', function () {
        $res = $this->getJson('/api/locale/en');

        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);
        expect($res->json('data.lang'))->toBe('en');
        expect($res->json('data.md5'))->toMatch('/^[a-f0-9]{32}$/');
        $items = $res->json('data.items');
        expect($items['api.success'])->toBe('Operation success');
    });

    it('falls back to ?lang query when path is omitted', function () {
        $res = $this->getJson('/api/locale?lang=en');

        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);
        expect($res->json('data.lang'))->toBe('en');
    });

    it('rejects illegal lang with code 1004', function () {
        $res = $this->getJson('/api/locale/xx_NOT_EXIST');

        $res->assertOk()->assertJson(['success' => false, 'code' => 1004]);
    });

    it('blocks path traversal in lang segment', function () {
        // Traversal attempts are treated as unknown locale -> 1004/目录穿越按未知语言处理 -> 1004
        $res = $this->getJson('/api/locale/..%2F..%2Fetc');

        $res->assertOk()->assertJson(['success' => false, 'code' => 1004]);
    });

    it('meta endpoint returns md5 matching pack', function () {
        $pack = $this->getJson('/api/locale/zh-CN')->json('data');
        $meta = $this->getJson('/api/locale/zh-CN/meta')->json('data');

        expect($meta)->toBe(['lang' => 'zh-CN', 'md5' => $pack['md5']]);
        expect($meta['md5'])->toMatch('/^[a-f0-9]{32}$/');
        // meta must not leak items/meta 不得泄露 items
        expect($meta)->not->toHaveKey('items');
    });

    it('rejects admin refresh without auth (code 1002)', function () {
        $this->postJson('/api/admin/locale/refresh')
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 1002]);
    });

    it('allows super admin to refresh all locales (code 0)', function () {
        $headers = $this->loginAdmin();

        $res = $this->withHeaders($headers)->postJson('/api/admin/locale/refresh');

        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);
        expect($res->json('data.refreshed'))->toBeInt()->toBeGreaterThan(0);
    });

    it('lists available locales for admin', function () {
        $headers = $this->loginAdmin();

        $res = $this->withHeaders($headers)->getJson('/api/admin/locale/languages');

        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);
        expect($res->json('data.list'))->toContain('zh-CN', 'en');
    });

    it('migrates legacy flat-items cache payload without md5', function () {
        // Simulate an old cache entry written before md5 was added/
        // 模拟早期版本写入的旧格式缓存（纯 items，无 md5）
        \Illuminate\Support\Facades\Cache::put(
            'locale:pack:zh-CN',
            ['api.success' => '操作成功', 'api.login.success' => '登录成功'],
            60,
        );

        $res = $this->getJson('/api/locale/zh-CN');

        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);
        expect($res->json('data.md5'))->toMatch('/^[a-f0-9]{32}$/');
        // items keys contain literal dots (e.g. "api.success"), so bracket access./
        // items 的 key 自带点号（如 "api.success"），需用数组下标读取。
        $items = $res->json('data.items');
        expect($items['api.success'])->toBe('操作成功');

        // meta endpoint should also work after migration/meta 端点同样可用
        $meta = $this->getJson('/api/locale/zh-CN/meta')->json('data');
        expect($meta['md5'])->toBe($res->json('data.md5'));
    });

    it('success endpoints return success=true with code=0 (response shape contract)', function () {
        // Regression: BaseController::success() previously defaulted to code=200,
        // producing {success:false, code:200, msg:"操作成功"} — contradictory shape./
        // 回归：BaseController::success() 默认 code=200 导致 success:false + code:200 矛盾。
        $this->getJson('/api/locale/zh-CN')
            ->assertOk()
            ->assertJson(['success' => true, 'code' => 0]);

        $this->getJson('/api/locale/zh-CN/meta')
            ->assertOk()
            ->assertJson(['success' => true, 'code' => 0]);
    });

    it('pack includes ui.* keys for both zh-CN and en', function () {
        // Frontend UI copy must be served by the pack (not bundled in client code)./
        // 前端 UI 文案必须由语言包接口下发（而非前端硬编码 fallback）。
        $zh = $this->getJson('/api/locale/zh-CN')->json('data.items');
        $en = $this->getJson('/api/locale/en')->json('data.items');

        // Top-level labels/顶层通用
        expect($zh['ui.home'])->toBe('首页');
        expect($zh['ui.confirm'])->toBe('确认');
        expect($zh['ui.cancel'])->toBe('取消');
        expect($zh['ui.refresh_locale'])->toBe('刷新语言包');

        // Module groups/各模块
        expect($zh['ui.login.title'])->toBe('Laravel13 管理后台');
        expect($zh['ui.rate.title'])->toBe('限流规则');
        expect($zh['ui.rate.create'])->toBe('新增规则');
        expect($zh['ui.rate.remark'])->toBe('备注');
        expect($zh['ui.admin.title'])->toBe('管理员管理');
        expect($zh['ui.recycle.title'])->toBe('回收站');

        expect($en['ui.home'])->toBe('Home');
        expect($en['ui.confirm'])->toBe('OK');
        expect($en['ui.login.title'])->toBe('Laravel13 Admin');
        expect($en['ui.rate.title'])->toBe('Rate Limits');
        expect($en['ui.rate.create'])->toBe('New rule');
    });
});
