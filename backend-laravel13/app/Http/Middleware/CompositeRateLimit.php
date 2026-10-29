<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Base\BaseMiddleware;
use Closure;
use App\Exceptions\ApiException;
use App\Services\Admin\RateLimitRuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Composite rate limiter/复合限流中间件
 *
 * Counts requests per route path + client IP + actor (logged-in user id or
 * browser fingerprint). Key shape: composite_rate:{path}:{ip}:{uid_id|fp_fp}.
 * Uses Cache facade (default store) so the counter is shared across instances
 * and testable with the array driver. Throws 429 when exceeded.
 * Extends BaseMiddleware; reuses guard() for current-guard detection.
 *
 * Rule resolution/规则解析:
 * - Mounted with explicit args (`api.composite.rate:10,60`): args override the
 *   dynamic table (kept as an escape hatch for per-route hard caps).
 * - Mounted without args: delegates to RateLimitRuleService::resolve(routeName,
 *   path, guard) which matches against the configurable rate_limit_rules table
 *   and falls back to DEFAULT_MAX/DEFAULT_DECAY.
 *
 * 按路由路径 + 客户端 IP + 行为主体（登录用户 id 或浏览器指纹）计数。
 * Key 形状：composite_rate:{path}:{ip}:{uid_id|fp_fp}。
 * 通过 Cache facade（默认 store）操作，多实例共享计数、测试可用 array 驱动。
 * 超限时抛 429。
 * 继承 BaseMiddleware，复用 guard() 判定当前守卫。
 *
 * 规则解析：
 * - 显式传参（api.composite.rate:10,60）：参数覆盖后台配置（保留硬编码逃生口）。
 * - 无参挂载：委托 RateLimitRuleService::resolve(routeName, path, guard) 匹配
 *   rate_limit_rules 后台配置表，无匹配兜底 DEFAULT_MAX/DEFAULT_DECAY。
 *
 * Middleware alias/中间件别名: api.composite.rate[:{maxAttempts},{decaySeconds}]
 */
class CompositeRateLimit extends BaseMiddleware
{
    /** Default max attempts when no rule matches/无规则命中时的默认最大次数 */
    private const DEFAULT_MAX_ATTEMPTS = 60;

    /** Default decay window (seconds) when no rule matches/无规则命中时的默认时间窗秒数 */
    private const DEFAULT_DECAY_SECONDS = 60;

    /**
     * Handle an incoming request/处理请求
     *
     * @param  mixed ...$args  optional [maxAttempts, decaySeconds] override/可选 [最大次数, 时间窗秒数] 覆盖参数
     */
    public function handle(Request $request, Closure $next, mixed ...$args): Response
    {
        // Resolve [max, decay]: explicit args win, otherwise dynamic rule service
        // 解析 [最大次数, 时间窗]：显式参数优先，否则走动态规则服务
        [$maxAttempts, $decaySeconds] = $this->resolveQuota($request, $args);

        // Resolve actor identifier: uid_{id} for logged-in users, fp_{fingerprint} for guests
        // 解析行为主体：登录用户 uid_{id}，游客 fp_{fingerprint}
        $identifier = $this->resolveIdentifier($request);

        // Build composite cache key: composite_rate:{path}:{ip}:{actor}
        // 组装复合缓存 key：composite_rate:{path}:{ip}:{actor}
        $key = sprintf(
            'composite_rate:%s:%s:%s',
            $request->path(),
            client_ip(),
            $identifier
        );

        // Atomic-ish init: write 0 with TTL if missing, leave untouched if exists, then increment
        // 近似原子初始化：key 不存在时写入 0（带 TTL），存在则不动，随后 increment
        Cache::add($key, 0, $decaySeconds);
        $hits = (int) Cache::increment($key, 1);

        // Remaining quota clamped at 0/剩余配额最小为 0
        $remaining = max(0, $maxAttempts - $hits);

        // Over quota: reject with 429 before touching business logic/超限：在业务前直接抛 429
        if ($hits > $maxAttempts) {
            throw ApiException::tooMany('api.rate_limited');
        }

        $response = $next($request);

        // Attach standard rate-limit headers to the response/响应挂载标准限流头
        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);

        return $response;
    }

    /**
     * Resolve [maxAttempts, decaySeconds] quota/解析 [最大次数, 时间窗] 配额
     *
     * Explicit middleware args take precedence (escape hatch). With no args we
     * ask RateLimitRuleService to match the current route against the
     * configurable rate_limit_rules table; if anything goes wrong or no rule
     * matches, fall back to DEFAULT_* constants.
     * 显式中间件参数优先（逃生口）。无参时委托 RateLimitRuleService 按当前路由
     * 匹配后台 rate_limit_rules 配置；异常或无命中时回退 DEFAULT_* 常量。
     *
     * @param  array<int,mixed> $args variadic args from middleware mount/中间件挂载传入的可变参数
     * @return array{0:int,1:int}      [maxAttempts, decaySeconds]/[最大次数, 时间窗秒数]
     */
    private function resolveQuota(Request $request, array $args): array
    {
        // Explicit args override dynamic rules: api.composite.rate:10,60
        // 显式参数覆盖动态规则：api.composite.rate:10,60
        if (count($args) >= 2) {
            return [(int) $args[0], (int) $args[1]];
        }

        // Resolve context for the rule matcher: route name, path, current guard (via base class helper)
        // 为规则匹配器准备上下文：路由名、路径、当前守卫（复用基类 guard()）
        $routeName = $request->route()?->getName();
        $path = $request->path();
        $guard = $this->guard($request);

        try {
            $rule = app(RateLimitRuleService::class)->resolve($routeName, $path, $guard);
            if (is_array($rule) && count($rule) >= 2) {
                return [(int) $rule[0], (int) $rule[1]];
            }
        } catch (\Throwable $e) {
            // Rule service failure must not block requests; fall back to defaults
            // 规则服务异常不得阻断请求：回退默认配额
            \Illuminate\Support\Facades\Log::warning('rate_limit_rule_resolve_failed', [
                'err'  => $e->getMessage(),
                'path' => $path,
            ]);
        }

        // No match / service down: use the built-in defaults/无命中 / 服务异常：使用内置默认值
        return [self::DEFAULT_MAX_ATTEMPTS, self::DEFAULT_DECAY_SECONDS];
    }

    /**
     * Resolve actor identifier by guard/按守卫解析行为主体
     *
     * Priority: api guard → admin-api guard → browser fingerprint header.
     * Uses base guard() to detect the current guard, then reads the id from it.
     * 优先级：api 守卫 → admin-api 守卫 → 浏览器指纹头。
     * 复用基类 guard() 判定当前守卫，再从对应守卫取 id。
     */
    private function resolveIdentifier(Request $request): string
    {
        $guard = $this->guard($request);

        // Logged-in on either guard: key by uid_{id}/登录用户：按 uid_{id} 作为主体
        if ($guard !== null) {
            return 'uid_' . auth($guard)->id();
        }

        // Guest: fall back to X-Client-Fingerprint header/游客：回退 X-Client-Fingerprint 头
        return 'fp_' . client_fingerprint($request);
    }
}
