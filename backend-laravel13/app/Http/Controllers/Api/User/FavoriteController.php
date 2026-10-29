<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\FavoriteStoreRequest;
use App\Services\User\FavoriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * C-end user favorite controller/C 端用户收藏控制器
 *
 * Full-isolation extension: C-end only, uses App\Models\UserFavorite.
 * MUST NOT reference any App\Models\AdminFavorite / App\Services\Admin\* / Admin-side class.
 * 全隔离扩展示例：仅 C 端，使用 UserFavorite 模型，禁止引用任何 Admin* 类。
 */
class FavoriteController extends Controller
{
    /**
     * Paginate my favorites/我的收藏分页
     *
     * Route #16: GET /api/favorites; sorted by created_at desc with product preloaded/
     * 路由 #16：GET /api/favorites，按 created_at 倒序，预加载商品
     *
     * @param Request          $request HTTP request/HTTP 请求
     * @param FavoriteService $service Favorite service/收藏服务
     */
    public function index(Request $request, FavoriteService $service): JsonResponse
    {
        $page     = max(1, (int) $request->query('page', 1));
        $pageSize = min(100, max(1, (int) $request->query('page_size', 15)));

        // Pass authenticated user id into service (no auth() read inside service)/
        // 身份 id 由控制器传入，Service 内不读 auth()
        $result = $service->list((int) $request->user()->getAuthIdentifier(), $page, $pageSize);

        return message('api.success', 0, $result);
    }

    /**
     * Favorite a product/收藏商品
     *
     * Route #15: POST /api/favorites; duplicate → 409 api.favorite.duplicate/
     * 路由 #15：POST /api/favorites，重复收藏 → 409 api.favorite.duplicate
     *
     * @param FavoriteStoreRequest $request Validated request/已校验请求
     * @param FavoriteService      $service Favorite service/收藏服务
     */
    public function store(FavoriteStoreRequest $request, FavoriteService $service): JsonResponse
    {
        $favorite = $service->add(
            (int) $request->user()->getAuthIdentifier(),
            (int) $request->validated('product_id')
        );

        return message('api.favorite.added', 0, $favorite);
    }

    /**
     * Check whether a product is favorited by me/查询我是否已收藏某商品
     *
     * Route #17: GET /api/favorites/status/{productId}; {productId} = product snow_id/
     * 路由 #17：GET /api/favorites/status/{productId}，{productId} 为商品 snow_id
     *
     * @param Request          $request       HTTP request/HTTP 请求
     * @param int              $productSnowId Product outward snow_id/商品对外 snow_id
     * @param FavoriteService  $service      Favorite service/收藏服务
     */
    public function status(Request $request, int $productSnowId, FavoriteService $service): JsonResponse
    {
        $result = $service->status(
            (int) $request->user()->getAuthIdentifier(),
            $productSnowId
        );

        return message('api.success', 0, $result);
    }

    /**
     * Cancel my favorite by snow_id/取消我的收藏（按收藏记录 snow_id）
     *
     * Route #18: DELETE /api/favorites/{id}; owner-only, not found → 404 api.favorite.not_found/
     * 路由 #18：DELETE /api/favorites/{id}，仅本人可删，不存在 → 404 api.favorite.not_found
     *
     * @param Request          $request  HTTP request/HTTP 请求
     * @param int              $snowId   Favorite outward snow_id/收藏记录对外 snow_id
     * @param FavoriteService  $service Favorite service/收藏服务
     */
    public function destroy(Request $request, int $snowId, FavoriteService $service): JsonResponse
    {
        $service->remove(
            (int) $request->user()->getAuthIdentifier(),
            $snowId
        );

        return message('api.favorite.removed', 0);
    }
}
