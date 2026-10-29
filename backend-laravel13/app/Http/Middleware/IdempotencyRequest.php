<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Base\BaseMiddleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency replay guard/幂等防重复提交中间件
 *
 * Active only on POST/PUT/DELETE. When the client sends an X-Idempotency-Key
 * header, the response (status + JSON body) is cached for a short TTL. Repeated
 * requests with the same key within that window return the cached response
 * without re-executing the business logic.
 * Extends BaseMiddleware; reuses guard() to pick the actor scope.
 * 仅作用于 POST/PUT/DELETE。客户端携带 X-Idempotency-Key 头时，
 * 响应（状态码 + JSON body）短期缓存；窗口内相同 key 的重复请求直接返回缓存响应，
 * 不重复执行业务。
 * 继承 BaseMiddleware，复用 guard() 选择主体范围。
 *
 * Middleware alias/中间件别名: idempotency
 */
class IdempotencyRequest extends BaseMiddleware
{
    /**
     * Handle an incoming request/处理请求
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only write methods are idempotent-guarded; GET/HEAD pass through untouched
        // 仅写操作需幂等保护；GET/HEAD 直接放行
        if (! in_array(strtoupper($request->method()), ['POST', 'PUT', 'DELETE'], true)) {
            return $next($request);
        }

        // Missing idempotency key header: replay protection not requested/未传幂等头：不启用防重放
        $key = $request->header('X-Idempotency-Key');
        if (! is_string($key) || $key === '') {
            return $next($request);
        }

        // Resolve actor scope and build cache key: idempotency:{METHOD}:{path}:{uid|ip}:{key}
        // 解析主体范围并组装缓存 key：idempotency:{METHOD}:{path}:{uid|ip}:{key}
        $uid = $this->resolveActor($request);
        $cacheKey = sprintf(
            'idempotency:%s:%s:%s:%s',
            strtoupper($request->method()),
            $request->path(),
            $uid,
            $key
        );

        // Cache hit: replay the stored response (status + body) verbatim
        // 缓存命中：原样重放缓存响应（status + body）
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && array_key_exists('status', $cached) && array_key_exists('body', $cached)) {
            return response()->json($cached['body'], (int) $cached['status']);
        }

        // Cache miss: execute the business logic/缓存未命中：执行业务
        $response = $next($request);

        // Decode response body to array for stable JSON re-serialization
        // 解码响应 body 为数组，便于后续稳定 JSON 重序列化
        $body = json_decode((string) $response->getContent(), true);
        if (! is_array($body)) {
            $body = ['raw' => (string) $response->getContent()];
        }

        // Persist status + body with configured TTL (default 600s)
        // 将 status + body 写入缓存，TTL 取配置（默认 600 秒）
        $ttl = (int) config('app.idempotency_ttl', 600);
        Cache::put($cacheKey, [
            'status' => $response->getStatusCode(),
            'body'   => $body,
        ], $ttl);

        return $response;
    }

    /**
     * Resolve actor scope for cache isolation/解析主体范围用于缓存隔离
     *
     * Logged-in users are keyed by uid_id; anonymous requests fall back to IP.
     * Uses base guard() to detect the current guard.
     * 登录用户按 uid_id 隔离；匿名请求回退到客户端 IP。
     * 复用基类 guard() 判定当前守卫。
     */
    private function resolveActor(Request $request): string
    {
        $guard = $this->guard($request);

        // Logged-in on either guard: isolate by uid_{id}/登录用户：按 uid_{id} 隔离
        if ($guard !== null) {
            return 'uid_' . auth($guard)->id();
        }

        // Anonymous: isolate by client IP/匿名：按客户端 IP 隔离
        return client_ip();
    }
}
