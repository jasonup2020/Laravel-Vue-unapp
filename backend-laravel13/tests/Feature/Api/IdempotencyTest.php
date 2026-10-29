<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Product;
use Tests\TestCase;

/**
 * Idempotency feature tests/幂等功能测试
 *
 * Verifies that repeating a write with the same X-Idempotency-Key replays the
 * cached response instead of executing the business logic twice.
 *
 * 验证携带相同 X-Idempotency-Key 重复写请求时回放缓存响应，
 * 而非重复执行业务逻辑。
 */
describe('IdempotencyTest idempotency/幂等', function () {
    it('replays the cached response for the same idempotency key', function () {
        $ctx = $this->loginUser();
        $product = Product::factory()->create(['status' => 1]);
        $key = 'idem-key-'.uniqid();

        // First write executes and caches the response/首次写执行业务并缓存响应
        $first = $this->withHeaders($ctx['headers'] + ['X-Idempotency-Key' => $key])
            ->postJson('/api/favorites', ['product_id' => $product->snow_id]);
        $first->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // Repeated write with the same key replays the cached response/相同 key 重复写回放缓存响应
        $second = $this->withHeaders($ctx['headers'] + ['X-Idempotency-Key' => $key])
            ->postJson('/api/favorites', ['product_id' => $product->snow_id]);

        // No conflict -> the second call did not re-execute/未返回冲突 -> 第二次未重复执行
        $second->assertOk()->assertJson(['success' => true, 'code' => 0]);
        expect($second->json())->toEqual($first->json());
    });

    it('still executes when a different idempotency key is supplied', function () {
        $ctx = $this->loginUser();
        $product = Product::factory()->create(['status' => 1]);

        $this->withHeaders($ctx['headers'] + ['X-Idempotency-Key' => 'k1'])
            ->postJson('/api/favorites', ['product_id' => $product->snow_id])
            ->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // A new key forces a real execution -> duplicate favorite code 1007/
        // 新 key 真实执行 -> 重复收藏 code 1007
        $this->withHeaders($ctx['headers'] + ['X-Idempotency-Key' => 'k2'])
            ->postJson('/api/favorites', ['product_id' => $product->snow_id])
            ->assertOk()->assertJson(['success' => false, 'code' => 1007]);
    });
});
