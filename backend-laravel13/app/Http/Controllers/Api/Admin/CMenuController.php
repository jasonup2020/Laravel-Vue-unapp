<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CMenuStoreRequest;
use App\Http\Requests\Admin\CMenuUpdateRequest;
use App\Services\Admin\CMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin C-end menu controller/管理端C端菜单控制器
 *
 * CRUD for C-end public navigation menus
 * C 端公开导航菜单 CRUD
 */
class CMenuController extends Controller
{
    public function __construct(private CMenuService $service)
    {
    }

    /**
     * Paginated C-end menu list/分页查询 C 端菜单
     */
    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $pageSize = (int) $request->query('page_size', 15);

        $result = $this->service->index($page, $pageSize);

        return message('api.success', 0, $result);
    }

    /**
     * Create a C-end menu/创建 C 端菜单
     */
    public function store(CMenuStoreRequest $request): JsonResponse
    {
        $menu = $this->service->store($request->validated());

        return message('api.success', 0, $menu);
    }

    /**
     * Update a C-end menu/更新 C 端菜单
     */
    public function update(CMenuUpdateRequest $request, int $id): JsonResponse
    {
        $menu = $this->service->update($id, $request->validated());

        return message('api.success', 0, $menu);
    }

    /**
     * Soft delete a C-end menu/软删除 C 端菜单
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->destroy($id);

        return message('api.success', 0);
    }
}
