<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FavoriteIndexRequest;
use App\Services\Admin\FavoriteService;
use Illuminate\Http\JsonResponse;

/**
 * Admin favorite controller/管理端收藏控制器
 *
 * Full-isolation extension: admin-side read-only list. MUST NOT reference any C-end
 * User* controller/service/model. Route #57: GET /api/admin/favorites.
 * 全隔离扩展示例：管理端只读列表，禁止引用任何 C 端 User* 类。路由 #57。
 */
class FavoriteController extends Controller
{
    /**
     * Paginate all favorites (admin view)/分页查询全部收藏（管理视角）
     *
     * Route #57: GET /api/admin/favorites; optional ?user_id=<user snow_id> filter/
     * 路由 #57：GET /api/admin/favorites，可选 ?user_id=<用户 snow_id> 过滤
     *
     * @param FavoriteIndexRequest $request Validated request/已校验请求
     * @param FavoriteService      $service Favorite service/收藏服务
     */
    public function index(FavoriteIndexRequest $request, FavoriteService $service): JsonResponse
    {
        $page     = max(1, (int) $request->query('page', 1));
        $pageSize = min(100, max(1, (int) $request->query('page_size', 15)));

        // user_id query param carries the outward snow_id of users/
        // user_id 查询参数对外语义为 users.snow_id
        $rawUserId  = $request->query('user_id');
        $userSnowId = $rawUserId === null ? null : (int) $rawUserId;

        $result = $service->index($userSnowId, $page, $pageSize);

        return message('api.success', 0, $result);
    }
}
