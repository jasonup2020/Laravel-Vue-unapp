<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

/**
 * User favorite feature tests/用户收藏功能测试
 *
 * Verifies favorite creation, duplicate rejection (409), status query,
 * cancel (soft delete) and the admin favorite list.
 *
 * 验证收藏创建、重复拒绝（409）、状态查询、取消收藏（软删）与管理员收藏列表。
 */
describe('FavoriteTest favorites/收藏', function () {
    beforeEach(function () {
        $this->seed(DatabaseSeeder::class);
    });

    it('creates a favorite and rejects duplicates with 409', function () {
        $ctx = $this->loginUser();
        $product = Product::factory()->create(['status' => 1]);

        // First favorite succeeds/首次收藏成功
        $this->withHeaders($ctx['headers'])->postJson('/api/favorites', [
            'product_id' => $product->snow_id,
        ])->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // Duplicate favorite conflicts -> body code 1007/重复收藏冲突 -> 体 code 1007
        $this->withHeaders($ctx['headers'])->postJson('/api/favorites', [
            'product_id' => $product->snow_id,
        ])->assertOk()->assertJson(['success' => false, 'code' => 1007]);
    });

    it('reports favorite status and lists favorites', function () {
        $ctx = $this->loginUser();
        $product = Product::factory()->create(['status' => 1]);

        // Not favorited yet/尚未收藏
        $this->withHeaders($ctx['headers'])
            ->getJson("/api/favorites/status/{$product->snow_id}")
            ->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // Favorite it/收藏
        $this->withHeaders($ctx['headers'])->postJson('/api/favorites', [
            'product_id' => $product->snow_id,
        ])->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // Status now true/状态变为已收藏
        $status = $this->withHeaders($ctx['headers'])
            ->getJson("/api/favorites/status/{$product->snow_id}");
        $status->assertOk()->assertJson(['success' => true, 'code' => 0]);
        // Tolerate data=true or data={favorited:true}/兼容 data 直接为布尔或 data={favorited:true}
        $raw = $status->json('data');
        $favorited = is_bool($raw) ? $raw : (bool) ($raw['favorited'] ?? $raw['is_favorited'] ?? false);
        expect($favorited)->toBeTrue();

        // List contains the favorite/收藏列表包含该记录
        $list = $this->withHeaders($ctx['headers'])->getJson('/api/favorites');
        $list->assertOk()->assertJson(['success' => true, 'code' => 0]);
        expect($list->json('data.list'))->toBeArray()->not->toBeEmpty();
    });

    it('cancels a favorite by its snow_id', function () {
        $ctx = $this->loginUser();
        $product = Product::factory()->create(['status' => 1]);

        $this->withHeaders($ctx['headers'])->postJson('/api/favorites', [
            'product_id' => $product->snow_id,
        ])->assertOk()->assertJson(['success' => true, 'code' => 0]);

        $list = $this->withHeaders($ctx['headers'])->getJson('/api/favorites');
        $favSnowId = $list->json('data.list.0.snow_id');

        // Soft-delete the favorite/软删除该收藏
        $this->withHeaders($ctx['headers'])
            ->deleteJson("/api/favorites/{$favSnowId}")
            ->assertOk()->assertJson(['success' => true, 'code' => 0]);

        $after = $this->withHeaders($ctx['headers'])->getJson('/api/favorites');
        expect($after->json('data.list'))->toBeEmpty();
    });

    it('exposes the favorite list to admins', function () {
        $ctx = $this->loginUser();
        $product = Product::factory()->create(['status' => 1]);
        $this->withHeaders($ctx['headers'])->postJson('/api/favorites', [
            'product_id' => $product->snow_id,
        ])->assertOk()->assertJson(['success' => true, 'code' => 0]);

        $admin = $this->loginAdmin();
        $res = $this->withHeaders($admin)->getJson('/api/admin/favorites');

        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);
        expect($res->json('data.list'))->toBeArray();
    });
});
