<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DictStoreRequest;
use App\Http\Requests\Admin\DictUpdateRequest;
use App\Services\Admin\DictService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin dict controller/管理端数据字典控制器
 *
 * Full CRUD; writes clear the corresponding type cache
 * 完整增删改查；写操作清理对应 type 缓存
 */
class DictController extends Controller
{
    /**
     * Paginate dict items/分页查询字典项
     *
     * @param Request     $request HTTP request/HTTP 请求
     * @param DictService $service Dict service/字典服务
     */
    public function index(Request $request, DictService $service): JsonResponse
    {
        $page     = max(1, (int) $request->query('page', 1));
        $pageSize = min(100, max(1, (int) $request->query('page_size', 15)));

        $result = $service->index($request->query('type'), $page, $pageSize);

        return message('api.success', 0, $result);
    }

    /**
     * Create a dict item/创建字典项
     *
     * @param DictStoreRequest $request Validated request/已校验请求
     * @param DictService      $service Dict service/字典服务
     */
    public function store(DictStoreRequest $request, DictService $service): JsonResponse
    {
        $dict = $service->store($request->validated());

        return message('api.dict.saved', 0, $dict);
    }

    /**
     * Update a dict item by snow_id/按 snow_id 更新字典项
     *
     * @param int               $id      Snowflake business ID/雪花业务ID
     * @param DictUpdateRequest $request Validated request/已校验请求
     * @param DictService       $service Dict service/字典服务
     */
    public function update(int $id, DictUpdateRequest $request, DictService $service): JsonResponse
    {
        $dict = $service->update($id, $request->validated());

        return message('api.dict.updated', 0, $dict);
    }

    /**
     * Physically delete a dict item by snow_id/按 snow_id 物理删除字典项
     *
     * @param int         $id      Snowflake business ID/雪花业务ID
     * @param DictService $service Dict service/字典服务
     */
    public function destroy(int $id, DictService $service): JsonResponse
    {
        $service->destroy($id);

        return message('api.dict.deleted', 0);
    }
}
