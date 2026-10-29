<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Services\User\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * C-end product controller/C 端商品控制器
 *
 * Read-only: on-shelf product list + detail
 * 只读：上架商品列表 + 详情
 */
class ProductController extends Controller
{
    /**
     * List on-shelf products/分页获取上架商品
     *
     * @param Request         $request HTTP request/HTTP 请求
     * @param ProductService  $service Product service/商品服务
     */
    public function index(Request $request, ProductService $service): JsonResponse
    {
        $page     = max(1, (int) $request->query('page', 1));
        $pageSize = min(100, max(1, (int) $request->query('page_size', 15)));

        $result = $service->list($request->query('name'), $page, $pageSize);

        return message('api.success', 0, $result);
    }

    /**
     * Show on-shelf product detail by snow_id/按 snow_id 获取上架商品详情
     *
     * @param int             $id      Snowflake business ID/雪花业务ID
     * @param ProductService  $service Product service/商品服务
     */
    public function show(int $id, ProductService $service): JsonResponse
    {
        $product = $service->showBySnowId($id);

        return message('api.success', 0, $product);
    }
}
