<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Dict;
use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

/**
 * Product and dictionary feature tests/商品与字典功能测试
 *
 * Verifies the C-end read-only product list (on-sale only, snow_id exposed),
 * the dictionary cache hit, and admin product CRUD.
 *
 * 验证 C 端只读商品列表（仅上架、暴露 snow_id）、字典缓存命中，以及管理员商品 CRUD。
 */
describe('ProductDictTest products and dicts/商品与字典', function () {
    beforeEach(function () {
        $this->seed(DatabaseSeeder::class);
    });

    it('lists only on-sale products for C-end and exposes snow_id', function () {
        // Clear seeded products first (DemoSeeder seeds its own)/
        // 先清空种子商品（DemoSeeder 自带演示数据），软删表需 forceDelete
        Product::query()->forceDelete();

        // 3 on-sale + 1 off-sale products/3 条上架 + 1 条下架商品
        Product::factory()->count(3)->create(['status' => 1]);
        Product::factory()->create(['status' => 0]);

        $res = $this->getJson('/api/products');

        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // Pagination envelope {list,total,page,page_size}/分页结构 {list,total,page,page_size}
        expect($res->json('data.total'))->toBe(3);
        expect($res->json('data.list'))->toBeArray()->toHaveCount(3);

        // Every exposed row uses snow_id (15-16 digits), no auto-increment id/
        // 每条对外记录使用 snow_id（15-16 位），不含自增 id
        $first = $res->json('data.list.0');
        expect($first)->toHaveKey('snow_id');
        expect($first['snow_id'])->toBeGreaterThan(100000000000000);
        expect($first)->not->toHaveKey('id');
    });

    it('serves the same dictionary payload on repeated cached reads', function () {
        Dict::factory()->count(3)->create(['type' => 'gender', 'status' => 1]);

        // First request warms the dict cache/首次请求预热字典缓存
        $first = $this->getJson('/api/dicts/gender');
        $first->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // Second request must return the identical cached payload/二次请求须返回相同缓存数据
        $second = $this->getJson('/api/dicts/gender');

        expect($second->json('data'))->toEqual($first->json('data'));
    });

    it('supports admin product create, update and soft delete', function () {
        // Clear seeded products so assertions stay deterministic/
        // 清空种子商品使断言不受演示数据干扰
        Product::query()->forceDelete();

        $admin = $this->loginAdmin();

        // Create/新建
        $create = $this->withHeaders($admin)->postJson('/api/admin/products', [
            'name' => 'Probe Product',
            'desc' => 'created by test',
            'price' => 99.50,
            'status' => 1,
        ]);
        $create->assertOk()->assertJson(['success' => true, 'code' => 0]);

        $snowId = $create->json('data.snow_id') ?? $create->json('data.id');
        expect($snowId)->toBeNumeric();

        // Update/更新
        $this->withHeaders($admin)->putJson("/api/admin/products/{$snowId}", [
            'name' => 'Probe Product v2',
            'desc' => 'updated',
            'price' => 120.00,
            'status' => 1,
        ])->assertOk()->assertJson(['success' => true, 'code' => 0]);

        expect(Product::where('snow_id', $snowId)->value('name'))->toBe('Probe Product v2');

        // Soft delete/软删除
        $this->withHeaders($admin)->deleteJson("/api/admin/products/{$snowId}")
            ->assertOk()->assertJson(['success' => true, 'code' => 0]);
        expect(Product::where('snow_id', $snowId)->exists())->toBeFalse();
        // Row still exists in trashed state/软删后记录仍在（软删除态）
        expect(Product::withTrashed()->where('snow_id', $snowId)->exists())->toBeTrue();
    });
});
