<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Exceptions\ApiException;
use App\Models\RateLimitRule;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * Rate limit rule service/限流规则服务
 *
 * Runtime rule resolution for the api.composite.rate middleware, plus admin-side
 * CRUD. All Redis interaction goes through the Cache facade; the cached rule
 * set uses a fixed key 'rate_limit:rules' with TTL 300s.
 * 为 api.composite.rate 中间件提供运行时规则解析，同时提供后台 CRUD。
 * 所有 Redis 交互走 Cache facade；缓存键固定 'rate_limit:rules'，TTL 300 秒。
 */
class RateLimitRuleService
{
    /**
     * Cache key for the full enabled-rules map/生效规则全集缓存键
     */
    private const CACHE_KEY = 'rate_limit:rules';

    /**
     * Cache TTL in seconds/缓存秒数
     */
    private const CACHE_TTL = 300;

    /**
     * Fallback [max, decay] when no rule matches/无匹配时的兜底 [最大次数, 窗口秒数]
     *
     * @var array{0:int,1:int}
     */
    private const FALLBACK = [60, 60];

    /**
     * Resolve [maxAttempts, decaySeconds] for the current request/解析当前请求的 [最大次数, 窗口秒数]
     *
     * Match order (highest priority first):
     *  1. Exact route-name match (rule_key === routeName)
     *  2. Exact path match (rule_key === path; path has no leading slash, e.g. 'api/login')
     *  3. Prefix match on path: rule_keys WITHOUT ':' are treated as path patterns
     *     (e.g. 'api/products'); pick the longest one that is a prefix of the request path.
     *     rule_keys WITH ':' (e.g. 'api:global', 'admin:global') are named/global rules
     *     and are skipped here (handled by step 1 or step 4).
     *  4. Guard-based global: admin guard → admin:global, others → api:global
     *  5. Final fallback [60, 60]
     * 匹配顺序（优先级从高到低）：
     *  1. 路由名精确匹配（rule_key === routeName）
     *  2. 请求路径精确匹配（rule_key === path；path 无前导斜杠，如 'api/login'）
     *  3. 路径前缀匹配：不含 ':' 的 rule_key 视为路径型（如 'api/products'），
     *     取最长前缀匹配当前请求路径；含 ':' 的 rule_key（如 'api:global'、
     *     'admin:global'）视为命名/全局规则，跳过此步（由第 1 或第 4 步处理）。
     *  4. 按守卫取全局：admin 守卫 → admin:global，其他 → api:global
     *  5. 最终兜底 [60, 60]
     *
     * @param  string|null $routeName named route of the current request/当前请求的路由名
     * @param  string      $path     request path (no leading slash, e.g. 'api/admin/products')/请求路径（无前导斜杠，如 'api/admin/products'）
     * @param  string|null $guard    current auth guard name (api / admin-api)/当前认证守卫
     * @return array{0:int,1:int}    [maxAttempts, decaySeconds]/[最大次数, 窗口秒数]
     */
    public function resolve(?string $routeName, string $path, ?string $guard): array
    {
        // Load all enabled rules indexed by rule_key / 加载全部启用规则并按 rule_key 索引
        $rules = $this->loadRules();

        // 1. Exact route-name match / 路由名精确匹配
        if ($routeName !== null && isset($rules[$routeName])) {
            return $this->toPair($rules[$routeName]);
        }

        // 2. Exact path match / 请求路径精确匹配
        if (isset($rules[$path])) {
            return $this->toPair($rules[$path]);
        }

        // 3. Prefix match: among rule_keys WITHOUT ':' (path-type patterns like
        //    'api/products'), pick the longest one that is a prefix of the request path.
        //    rule_keys WITH ':' (e.g. 'api:global') are named/global rules, skipped here.
        //    前缀匹配：在不含 ':' 的 rule_key（路径型，如 'api/products'）中，
        //    取最长前缀匹配当前请求路径的规则；含 ':' 的 rule_key（如 'api:global'）
        //    为命名/全局规则，跳过此步。
        $bestPrefixLen = 0;
        $bestPrefixRule = null;
        foreach ($rules as $key => $row) {
            // Skip named/global rules that contain ':'/跳过含 ':' 的命名/全局规则
            if (str_contains($key, ':')) {
                continue;
            }
            // Path-type key: check if it is a prefix of the request path/
            // 路径型 key：检查是否为请求路径的前缀
            if (str_starts_with($path, $key)) {
                if (strlen($key) > $bestPrefixLen) {
                    $bestPrefixLen = strlen($key);
                    $bestPrefixRule = $row;
                }
            }
        }
        if ($bestPrefixRule !== null) {
            return $this->toPair($bestPrefixRule);
        }

        // 4. Guard-based global rule / 按守卫取全局规则
        //    admin guard (admin-api) → admin:global; anything else → api:global
        //    admin 守卫（admin-api）→ admin:global；其他 → api:global
        $globalKey = ($guard !== null && str_starts_with($guard, 'admin')) ? 'admin:global' : 'api:global';
        if (isset($rules[$globalKey])) {
            return $this->toPair($rules[$globalKey]);
        }

        // 5. Final fallback / 最终兜底
        return self::FALLBACK;
    }

    /**
     * Paginate rate-limit rules/限流规则分页列表
     *
     * @param  array{rule_key?: string|null, enabled?: bool|null} $filters  optional filters/可选过滤条件
     * @param  int $page    page number/页码
     * @param  int $pageSize page size, clamped to <=100/每页条数，上限 100
     * @return array{list: \Illuminate\Pagination\LengthAwarePaginator, total: int, page: int, page_size: int}
     */
    public function index(array $filters, int $page, int $pageSize): array
    {
        // Clamp page size to 1..100/每页条数钳制在 1..100
        $pageSize = max(1, min(100, $pageSize));

        $query = RateLimitRule::query()->orderByDesc('id');

        // Optional fuzzy rule_key filter/可选 rule_key 模糊过滤
        if (!empty($filters['rule_key'])) {
            $query->where('rule_key', 'like', '%' . $filters['rule_key'] . '%');
        }

        // Optional enabled filter/可选启用状态过滤
        if (isset($filters['enabled'])) {
            $query->where('enabled', $filters['enabled'] ? 1 : 0);
        }

        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

        return [
            'list'      => $paginator->items(),
            'total'     => $paginator->total(),
            'page'      => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * Create a new rate-limit rule/新增限流规则
     *
     * @param  array{rule_key: string, max_attempts: int, decay_seconds: int, enabled?: bool, remark?: string|null} $data
     * @return RateLimitRule
     */
    public function store(array $data): RateLimitRule
    {
        $model = RateLimitRule::create([
            'rule_key'      => $data['rule_key'],
            'max_attempts'  => $data['max_attempts'],
            'decay_seconds' => $data['decay_seconds'],
            'enabled'       => $data['enabled'] ?? true,
            'remark'        => $data['remark'] ?? null,
        ]);

        // Invalidate cached rule set so the new rule takes effect immediately/
        // 失效规则缓存，新规则立即生效
        $this->forgetCache();

        return $model;
    }

    /**
     * Update an existing rate-limit rule by auto-increment id/按自增 id 更新限流规则
     *
     * @param  int   $id   auto-increment primary key/自增主键
     * @param  array{rule_key?: string, max_attempts?: int, decay_seconds?: int, enabled?: bool, remark?: string|null} $data
     * @return RateLimitRule
     */
    public function update(int $id, array $data): RateLimitRule
    {
        $model = RateLimitRule::query()->find($id);
        if ($model === null) {
            throw ApiException::notFound('api.not_found');
        }

        // Only fill fields present in the validated payload (partial update)/
        // 仅填充校验入参中实际存在的字段（局部更新）
        $fillable = [];
        foreach (['rule_key', 'max_attempts', 'decay_seconds', 'enabled', 'remark'] as $field) {
            if (array_key_exists($field, $data)) {
                $fillable[$field] = $data[$field];
            }
        }
        $model->fill($fillable);
        $model->save();

        // Invalidate cached rule set/失效规则缓存
        $this->forgetCache();

        return $model;
    }

    /**
     * Delete a rate-limit rule by auto-increment id/按自增 id 删除限流规则
     */
    public function destroy(int $id): void
    {
        $model = RateLimitRule::query()->find($id);
        if ($model === null) {
            throw ApiException::notFound('api.not_found');
        }

        $model->delete();

        // Invalidate cached rule set/失效规则缓存
        $this->forgetCache();
    }

    // ---------------------------------------------------------------------
    // Internal helpers/内部辅助方法
    // ---------------------------------------------------------------------

    /**
     * Load all enabled rules indexed by rule_key from cache or DB/
     * 从缓存或数据库加载全部启用规则，按 rule_key 索引
     *
     * @return array<string, array{rule_key:string, max_attempts:int, decay_seconds:int, enabled:bool}>
     */
    private function loadRules(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            $rows = RateLimitRule::query()
                ->where('enabled', true)
                ->get(['rule_key', 'max_attempts', 'decay_seconds', 'enabled']);

            $indexed = [];
            foreach ($rows as $row) {
                $indexed[$row->rule_key] = [
                    'rule_key'      => $row->rule_key,
                    'max_attempts'  => (int) $row->max_attempts,
                    'decay_seconds' => (int) $row->decay_seconds,
                    'enabled'       => (bool) $row->enabled,
                ];
            }
            return $indexed;
        });
    }

    /**
     * Convert a cached rule row to [max, decay] pair/将缓存规则行转为 [最大次数, 窗口秒数]
     *
     * @param  array{max_attempts:int, decay_seconds:int} $row
     * @return array{0:int,1:int}
     */
    private function toPair(array $row): array
    {
        return [(int) $row['max_attempts'], (int) $row['decay_seconds']];
    }

    /**
     * Invalidate the cached rule set/失效规则缓存
     */
    private function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
