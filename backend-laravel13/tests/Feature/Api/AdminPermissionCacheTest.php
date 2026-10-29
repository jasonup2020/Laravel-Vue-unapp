<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Admin;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * AdminPermission cache serialization regression test/AdminPermission 缓存序列化回归测试
 *
 * Guards against a production bug where the middleware cached an
 * Illuminate\Support\Collection into Redis; on cache hit in a later request
 * the unserialized object came back as __PHP_Incomplete_Class and calling
 * ->contains() on it fatalled, surfacing as code:5000 on every admin endpoint.
 * The fix caches a PLAIN PHP array instead.
 *
 * 守护一个线上 bug：旧实现把 Illuminate\Support\Collection 缓存进 Redis，
 * 后续请求读缓存时反序列化得到 __PHP_Incomplete_Class，调用 ->contains() 直接 fatal，
 * 表现为所有管理端接口 code:5000。修复后缓存纯 PHP 数组。
 *
 * Covers both cache paths:
 *  - cold:  Cache::forget() then first request (rebuilds from DB)
 *  - hot:   second request hits the cached array
 * 覆盖两条缓存路径：
 *  - 冷：  Cache::forget() 后首次请求（从 DB 重建）
 *  - 热：  二次请求命中已缓存数组
 */
describe('AdminPermissionCacheTest permission cache/权限缓存', function () {
    beforeEach(function () {
        $this->seed(DatabaseSeeder::class);
    });

    it('serves /api/admin/products on both cold and hot permission cache paths', function () {
        // super-admin id is seeded as username=admin/super-admin role/
        // 种子超管理员 username=admin，绑定 super-admin 角色
        $admin = Admin::query()->where('username', 'admin')->firstOrFail();
        $cacheKey = "admin:permissions:{$admin->id}";

        $headers = $this->loginAdmin();

        // ---- Cold path: drop any cached permission set, then first request ----
        // 冷路径：先清空权限缓存，再发起首次请求
        Cache::forget($cacheKey);
        expect(Cache::get($cacheKey))->toBeNull();

        $cold = $this->withHeaders($headers)->getJson('/api/admin/products');
        $cold->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // DemoSeeder seeds 5 products/DemoSeeder 播种 5 条商品
        expect($cold->json('data.total'))->toBe(5);
        expect($cold->json('data.list'))->toBeArray()->toHaveCount(5);

        // After the cold request the cache MUST hold a plain array, not a Collection/
        // 冷请求后缓存必须为纯数组，不能是 Collection 实例
        $cached = Cache::get($cacheKey);
        expect($cached)->toBeArray();
        expect($cached)->toContain('*'); // super-admin wildcard/超管通配符

        // ---- Hot path: second request hits the cached array ----
        // 热路径：二次请求直接命中已缓存数组
        $hot = $this->withHeaders($headers)->getJson('/api/admin/products');
        $hot->assertOk()->assertJson(['success' => true, 'code' => 0]);
        expect($hot->json('data.total'))->toBe(5);
        expect($hot->json('data.list'))->toBeArray()->toHaveCount(5);
    });

    it('caches a plain array (not a Collection) for a non-super operator', function () {
        // demo admin is bound to the operator role (product/dict only)/
        // demo 管理员绑定 operator 角色（仅商品/字典）
        $operator = Admin::query()->where('username', 'demo')->firstOrFail();
        $cacheKey = "admin:permissions:{$operator->id}";

        $headers = $this->loginAdmin('demo', 'demo123');

        Cache::forget($cacheKey);

        // First request warms the cache/首次请求预热缓存
        $this->withHeaders($headers)->getJson('/api/admin/products')
            ->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // The cached payload must be a plain array of permission code strings/
        // 缓存载荷必须是纯权限码字符串数组
        $cached = Cache::get($cacheKey);
        expect($cached)->toBeArray();
        expect($cached)->not->toContain('*'); // operator is NOT super-admin/operator 非超管
        expect($cached)->toContain('admin:product:list');

        // Hot path still 200/热路径仍 200
        $this->withHeaders($headers)->getJson('/api/admin/products')
            ->assertOk()->assertJson(['success' => true, 'code' => 0]);
    });
});
