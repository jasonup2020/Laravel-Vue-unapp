<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Health check controller/健康检查控制器
 *
 * Public, guard-less endpoint GET /api/health. Probes DB, cache store and Redis
 * independently; every probe is wrapped in try/catch and reports "down" instead
 * of throwing a stack trace. Returns HTTP 503 when the database is unreachable.
 * 公开无守卫接口 GET /api/health。分别探针 DB、缓存存储与 Redis；
 * 每个探针 try/catch，失败返回 down 状态而非抛堆栈。数据库不可达时返回 503。
 */
class HealthController extends Controller
{
    /**
     * Health check endpoint/健康检查接口
     */
    public function index(): JsonResponse
    {
        $db    = $this->probeDb();
        $cache = $this->probeCache();
        $redis = $this->probeRedis();

        $data = [
            'app'   => 'up',
            'env'   => app()->environment(),
            'db'    => $db,
            'redis' => $redis,
            'cache' => $cache,
            'time'  => now()->toIso8601String(),
        ];

        // Database is the critical dependency: down -> 503/数据库为关键依赖：down 时返回 503
        $healthy = $db === 'up';

        return message($healthy ? 'api.health.ok' : 'api.server_error', $healthy ? 0 : 5000, $data);
    }

    /**
     * Probe database connectivity via `select 1`/用 `select 1` 探针数据库连通性
     */
    private function probeDb(): string
    {
        try {
            DB::select('select 1');
            return 'up';
        } catch (\Throwable) {
            return 'down';
        }
    }

    /**
     * Probe the default cache store with a write/read round-trip/
     * 用写读回环探针默认缓存存储
     */
    private function probeCache(): string
    {
        try {
            Cache::put('health:probe', 'ok', 5);
            return Cache::get('health:probe') === 'ok' ? 'up' : 'down';
        } catch (\Throwable) {
            return 'down';
        }
    }

    /**
     * Probe Redis through the redis cache store (Cache facade only, per contract)/
     * 通过 redis 缓存存储探针 Redis（按契约仅用 Cache facade）
     */
    private function probeRedis(): string
    {
        try {
            Cache::store('redis')->put('health:redis', 'ok', 5);
            return Cache::store('redis')->get('health:redis') === 'ok' ? 'up' : 'down';
        } catch (\Throwable) {
            return 'down';
        }
    }
}
