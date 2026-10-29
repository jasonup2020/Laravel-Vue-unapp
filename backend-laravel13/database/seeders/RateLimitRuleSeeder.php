<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RateLimitRule;
use Illuminate\Database\Seeder;

/**
 * Rate limit rules seeder/限流规则播种器
 *
 * Seeds the default rate-limit rules consumed by the api.composite.rate
 * middleware. Idempotent via updateOrCreate on rule_key.
 *
 * rule_key 约定：路径型规则使用 $request->path() 格式（无前导斜杠，如
 * 'api/login'），以便 resolve() 的精确/前缀匹配命中；全局兜底规则使用
 * 'api:global' / 'admin:global' 命名（含冒号），由 resolve() 按守卫全局分支匹配。
 *
 * 播种 api.composite.rate 中间件使用的默认限流规则。
 * 通过 rule_key 键 updateOrCreate 保证幂等。
 * rule_key 约定：路径型规则使用 $request->path() 格式（无前导斜杠，如
 * 'api/login'），便于 resolve() 的精确/前缀匹配命中；全局兜底规则使用
 * 'api:global' / 'admin:global' 命名（含冒号），由 resolve() 按守卫全局分支匹配。
 */
class RateLimitRuleSeeder extends Seeder
{
    /**
     * Seed default rate-limit rules/播种默认限流规则
     *
     * Default rules (all enabled):
     *  - api:global        60/60    C-end global default (guard fallback, contains ':')
     *  - admin:global     120/60   admin-side global default (guard fallback, contains ':')
     *  - api/login         5/60    C-end login brute-force protection (path match)
     *  - api/admin/login   5/60    admin login brute-force protection (path match)
     *  - api/products      120/60   example: product list endpoint (path match)
     * 默认规则（全部启用）：
     *  - api:global        60/60    C 端全局默认（按守卫兜底，含冒号）
     *  - admin:global      120/60   管理端全局默认（按守卫兜底，含冒号）
     *  - api/login         5/60    C 端登录防爆破（路径匹配）
     *  - api/admin/login   5/60    管理端登录防爆破（路径匹配）
     *  - api/products      120/60   示例：商品列表接口（路径匹配）
     */
    public function run(): void
    {
        $defaults = [
            // C-end global default (guard fallback key, contains ':')/
            // C 端全局默认（按守卫兜底键，含冒号）
            ['rule_key' => 'api:global',       'max_attempts' => 60,  'decay_seconds' => 60, 'remark' => 'C-end global default rate limit (guard fallback)/C端全局默认限流（按守卫兜底）'],
            // Admin-side global default (guard fallback key, contains ':')/
            // 管理端全局默认（按守卫兜底键，含冒号）
            ['rule_key' => 'admin:global',     'max_attempts' => 120, 'decay_seconds' => 60, 'remark' => 'Admin-side global default rate limit (guard fallback)/管理端全局默认限流（按守卫兜底）'],
            // C-end login brute-force protection (path = api/login)/
            // C 端登录防爆破（路径 = api/login）
            ['rule_key' => 'api/login',         'max_attempts' => 5,   'decay_seconds' => 60, 'remark' => 'C-end login brute-force protection (path)/C端登录防爆破（路径匹配）'],
            // Admin login brute-force protection (path = api/admin/login)/
            // 管理端登录防爆破（路径 = api/admin/login）
            ['rule_key' => 'api/admin/login',   'max_attempts' => 5,   'decay_seconds' => 60, 'remark' => 'Admin login brute-force protection (path)/管理端登录防爆破（路径匹配）'],
            // Example: product list endpoint (path = api/products)/
            // 示例：商品列表接口（路径 = api/products）
            ['rule_key' => 'api/products',     'max_attempts' => 120, 'decay_seconds' => 60, 'remark' => 'Product list example rate limit (path)/商品列表示例限流（路径匹配）'],
        ];

        foreach ($defaults as $row) {
            // Idempotent upsert by rule_key/按 rule_key 幂等更新或创建
            RateLimitRule::updateOrCreate(
                ['rule_key' => $row['rule_key']],
                $row + ['enabled' => true]
            );
        }
    }
}
