<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IpBlacklistStoreRequest;
use App\Services\Admin\IpBlacklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * IP blacklist admin controller/IP 黑名单管理控制器
 *
 * Paginated list (optional fuzzy IP filter), create ban, and unban by snow_id.
 * Every write invalidates the middleware cache via the service.
 * 分页列表（可选 IP 模糊过滤）、新增封禁、按 snow_id 解封；
 * 每次写操作经 service 失效中间件缓存。
 */
class IpBlacklistController extends Controller
{
    /**
     * Paginate blacklist rows/黑名单分页列表
     */
    public function index(Request $request, IpBlacklistService $service): JsonResponse
    {
        $page     = max(1, (int) $request->input('page', 1));
        $pageSize = min(100, max(1, (int) $request->input('page_size', 15)));

        $ip = $request->input('ip');
        $ip = is_string($ip) && $ip !== '' ? $ip : null;

        return message('api.success', 0, $service->paginate($page, $pageSize, $ip));
    }

    /**
     * Create a blacklist entry/新增封禁
     */
    public function store(IpBlacklistStoreRequest $request, IpBlacklistService $service): JsonResponse
    {
        return message('api.ip.added', 0, $service->store($request->validated()));
    }

    /**
     * Unban (physically delete) a blacklist entry by snow_id/按 snow_id 解封（物理删除）
     */
    public function destroy(string $snowId, IpBlacklistService $service): JsonResponse
    {
        $service->destroyBySnowId((int) $snowId);

        return message('api.ip.removed', 0);
    }
}
