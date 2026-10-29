<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductStoreRequest;
use App\Http\Requests\Admin\ProductUpdateRequest;
use App\Services\Admin\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin product controller/管理端商品控制器
 *
 * Full CRUD; business logic delegated to ProductService
 * 完整增删改查；业务逻辑下沉到 ProductService
 */
class ProductController extends Controller
{
    /**
     * Paginate products/分页查询商品
     *
     * @param Request        $request HTTP request/HTTP 请求
     * @param ProductService $service Product service/商品服务
     */
    public function index(Request $request, ProductService $service): JsonResponse
    {
        $page     = max(1, (int) $request->query('page', 1));
        $pageSize = min(100, max(1, (int) $request->query('page_size', 15)));
        $status   = $request->query('status');
        $status   = $status === null ? null : (int) $status;

        $result = $service->index($status, $request->query('name'), $page, $pageSize);

        return message('api.success', 0, $result);
    }

    /**
     * Create a product/创建商品
     *
     * @param ProductStoreRequest $request Validated request/已校验请求
     * @param ProductService     $service Product service/商品服务
     */
    public function store(ProductStoreRequest $request, ProductService $service): JsonResponse
    {
        $product = $service->store($request->validated());

        return message('api.product.saved', 0, $product);
    }

    /**
     * Update a product by snow_id/按 snow_id 更新商品
     *
     * @param int                  $id      Snowflake business ID/雪花业务ID
     * @param ProductUpdateRequest $request Validated request/已校验请求
     * @param ProductService       $service Product service/商品服务
     */
    public function update(int $id, ProductUpdateRequest $request, ProductService $service): JsonResponse
    {
        $product = $service->update($id, $request->validated());

        return message('api.product.updated', 0, $product);
    }

    /**
     * Soft-delete a product by snow_id/按 snow_id 软删除商品
     *
     * @param int            $id      Snowflake business ID/雪花业务ID
     * @param ProductService $service Product service/商品服务
     */
    public function destroy(int $id, ProductService $service): JsonResponse
    {
        $service->destroy($id);

        return message('api.product.deleted', 0);
    }
}
