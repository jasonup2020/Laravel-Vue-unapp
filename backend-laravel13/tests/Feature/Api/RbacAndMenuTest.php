<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

/**
 * RBAC and dynamic menu feature tests/RBAC 与动态菜单功能测试
 *
 * Verifies the super-admin bypass, permission enforcement for a non-super
 * operator, and that menu changes invalidate the my-tree cache.
 *
 * 验证超管旁路、非超管 operator 的权限拦截，以及菜单变更后 my-tree 缓存失效重建。
 */
describe('RbacAndMenuTest RBAC and menu/RBAC 与菜单', function () {
    beforeEach(function () {
        $this->seed(DatabaseSeeder::class);
    });

    it('lets super-admin reach any admin endpoint without a specific permission', function () {
        $admin = $this->loginAdmin();

        // super-admin bypasses the admin.permission middleware -> 200 code 0/
        // 超管旁路 admin.permission 中间件 -> 200 code 0
        $this->withHeaders($admin)->getJson('/api/admin/products')
            ->assertOk()->assertJson(['success' => true, 'code' => 0]);
    });

    it('denies a non-super operator an endpoint outside its permissions', function () {
        // demo is bound to the operator role (product/dict only)/demo 绑定 operator 角色（仅商品/字典）
        $operator = $this->loginAdmin('demo', 'demo123');

        // admin:menu:list is not granted to operator -> body code 1003/
        // operator 未获 admin:menu:list -> 体 code 1003
        $this->withHeaders($operator)
            ->getJson('/api/admin/menu/all-tree')
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 1003]);
    });

    it('allows a non-super operator an endpoint inside its permissions', function () {
        $operator = $this->loginAdmin('demo', 'demo123');

        // operator holds admin:product:list -> 200 code 0/operator 持有 admin:product:list -> 200 code 0
        $this->withHeaders($operator)
            ->getJson('/api/admin/products')
            ->assertOk()->assertJson(['success' => true, 'code' => 0]);
    });

    it('returns the admin menu tree on my-tree', function () {
        $admin = $this->loginAdmin();

        $res = $this->withHeaders($admin)->getJson('/api/admin/menu/my-tree');

        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);
        // data is a non-empty tree array/data 为非空菜单树数组
        expect($res->json('data'))->toBeArray()->not->toBeEmpty();
    });

    it('refreshes the my-tree cache after a menu change', function () {
        $admin = $this->loginAdmin();

        // First read warms the cache/首次读取预热缓存
        $this->withHeaders($admin)->getJson('/api/admin/menu/my-tree')->assertOk();

        // Create a menu as super-admin; this must clear the menu cache/超管新建菜单，应清理菜单缓存
        $probeTitle = 'Cache Probe Menu '.uniqid();
        $this->withHeaders($admin)->postJson('/api/admin/menu', [
            'parent_id' => 0,
            'title' => $probeTitle,
            'path' => '/probe',
            'icon' => null,
            'sort' => 99,
            'is_show' => 1,
            'is_cache' => 1,
            'is_external' => 0,
        ])->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // Re-read; the rebuilt tree must contain the new entry/再次读取，重建后的菜单树须包含新条目
        $res = $this->withHeaders($admin)->getJson('/api/admin/menu/my-tree');
        $flat = json_encode($res->json('data'));

        expect($flat)->toContain($probeTitle);
    });
});
