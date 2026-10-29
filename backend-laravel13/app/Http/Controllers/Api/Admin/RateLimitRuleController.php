<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RateLimitRuleIndexRequest;
use App\Http\Requests\Admin\RateLimitRuleStoreRequest;
use App\Http\Requests\Admin\RateLimitRuleUpdateRequest;
use App\Services\Admin\RateLimitRuleService;
use Illuminate\Http\JsonResponse;

/**
 * Rate limit rule admin controller/限流规则管理控制器
 *
 * CRUD for the rate_limit_rules table. This table has no snow_id column, so
 * the resource identifier in route binding {id} is the auto-increment primary
 * key (per contract §3 row for rate_limit_rules).
 * rate_limit_rules 表 CRUD。本表无 snow_id 列，路由绑定 {id} 直接使用自增主键
 * （按契约第3节 rate_limit_rules 行定义）。
 */
class RateLimitRuleController extends Controller
{
    /**
     * Paginate rate-limit rules/限流规则分页列表
     */
    public function index(RateLimitRuleIndexRequest $request, RateLimitRuleService $service): JsonResponse
    {
        $validated = $request->validated();

        $page     = (int) ($validated['page'] ?? 1);
        $pageSize = (int) ($validated['page_size'] ?? 15);

        // Build optional filters/组装可选过滤条件
        $filters = [];
        if (!empty($validated['rule_key'])) {
            $filters['rule_key'] = $validated['rule_key'];
        }
        if (isset($validated['enabled'])) {
            $filters['enabled'] = (bool) $validated['enabled'];
        }

        return message('api.success', 0, $service->index($filters, $page, $pageSize));
    }

    /**
     * Create a rate-limit rule/新增限流规则
     */
    public function store(RateLimitRuleStoreRequest $request, RateLimitRuleService $service): JsonResponse
    {
        return message('api.success', 0, $service->store($request->validated()));
    }

    /**
     * Update a rate-limit rule by auto-increment id/按自增 id 更新限流规则
     */
    public function update(int $id, RateLimitRuleUpdateRequest $request, RateLimitRuleService $service): JsonResponse
    {
        return message('api.success', 0, $service->update($id, $request->validated()));
    }

    /**
     * Delete a rate-limit rule by auto-increment id/按自增 id 删除限流规则
     */
    public function destroy(int $id, RateLimitRuleService $service): JsonResponse
    {
        $service->destroy($id);

        return message('api.success', 0);
    }
}
