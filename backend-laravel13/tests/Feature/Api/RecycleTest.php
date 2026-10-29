<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

/**
 * Recycle bin feature tests/回收站功能测试
 *
 * Verifies soft-deleted products appear in the recycle bin, a single restore
 * and a batch restore bring them back.
 *
 * 验证软删商品进入回收站、单条恢复与批量恢复。
 */
describe('RecycleTest recycle bin/回收站', function () {
    beforeEach(function () {
        $this->seed(DatabaseSeeder::class);
    });

    it('lists soft-deleted products in the recycle bin', function () {
        $admin = $this->loginAdmin();
        $product = Product::factory()->create(['status' => 1]);

        // Soft delete via admin API/经管理员接口软删除
        $this->withHeaders($admin)
            ->deleteJson("/api/admin/products/{$product->snow_id}")
            ->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // Recycle bin lists the trashed row/回收站列出该软删记录
        $res = $this->withHeaders($admin)->getJson('/api/admin/recycle-bin/products');

        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);
        expect($res->json('data.list'))->toBeArray()->not->toBeEmpty();
    });

    it('restores a single trashed product', function () {
        $admin = $this->loginAdmin();
        $product = Product::factory()->create(['status' => 1]);

        $this->withHeaders($admin)->deleteJson("/api/admin/products/{$product->snow_id}")
            ->assertOk()->assertJson(['success' => true, 'code' => 0]);
        expect(Product::where('snow_id', $product->snow_id)->exists())->toBeFalse();

        // Restore by snow_id/按 snow_id 恢复
        $this->withHeaders($admin)
            ->putJson("/api/admin/recycle-bin/products/{$product->snow_id}/restore")
            ->assertOk()->assertJson(['success' => true, 'code' => 0]);

        expect(Product::where('snow_id', $product->snow_id)->exists())->toBeTrue();
    });

    it('restores multiple trashed products in batch', function () {
        $admin = $this->loginAdmin();
        $p1 = Product::factory()->create(['status' => 1]);
        $p2 = Product::factory()->create(['status' => 1]);

        $this->withHeaders($admin)->deleteJson("/api/admin/products/{$p1->snow_id}")
            ->assertOk()->assertJson(['success' => true, 'code' => 0]);
        $this->withHeaders($admin)->deleteJson("/api/admin/products/{$p2->snow_id}")
            ->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // Batch restore by snow_id list/按 snow_id 列表批量恢复
        $this->withHeaders($admin)
            ->putJson('/api/admin/recycle-bin/products/restore-batch', [
                'ids' => [$p1->snow_id, $p2->snow_id],
            ])
            ->assertOk()->assertJson(['success' => true, 'code' => 0]);

        expect(Product::whereIn('snow_id', [$p1->snow_id, $p2->snow_id])->count())->toBe(2);
    });

    it('rejects an unsupported recycle resource', function () {
        $admin = $this->loginAdmin();

        // dicts has no soft delete column and is not in the resource whitelist/
        // dicts 无软删列，不在资源白名单内 -> body code 1001 (invalid param)
        $this->withHeaders($admin)
            ->getJson('/api/admin/recycle-bin/dicts')
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 1001]);
    });
});
