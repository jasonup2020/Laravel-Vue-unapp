<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MenuStoreRequest;
use App\Http\Requests\Admin\MenuUpdateRequest;
use App\Services\Admin\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin menu controller/后台菜单控制器
 *
 * Handles personal menu tree (cached), full menu tree and menu CRUD
 * 处理个人菜单树（缓存）、全菜单树与菜单 CRUD
 */
class MenuController extends Controller
{
    public function __construct(private MenuService $service)
    {
    }

    /**
     * Current admin's menu tree via cache (route #24)/当前管理员菜单树（路由 #24，走缓存）
     */
    public function myTree(Request $request): JsonResponse
    {
        /** @var \App\Models\Admin $admin */
        $admin = $request->user('admin-api');
        $tree = $this->service->myTree($admin->id);

        return message('api.menu.my_tree', 0, ['tree' => $tree]);
    }

    /**
     * Full menu tree directly from DB (route #25)/全菜单树直读库（路由 #25）
     */
    public function allTree(): JsonResponse
    {
        $tree = $this->service->allTree();

        return message('api.menu.all_tree', 0, ['tree' => $tree]);
    }

    /**
     * Create menu (route #26)/创建菜单（路由 #26）
     */
    public function store(MenuStoreRequest $request): JsonResponse
    {
        $menu = $this->service->create($request->validated());

        return message('api.menu.saved', 0, $menu);
    }

    /**
     * Update menu (route #27)/更新菜单（路由 #27）
     */
    public function update(MenuUpdateRequest $request, int $id): JsonResponse
    {
        $menu = $this->service->update($id, $request->validated());

        return message('api.menu.updated', 0, $menu);
    }

    /**
     * Soft delete menu (route #28)/软删菜单（路由 #28）
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->destroy($id);

        return message('api.menu.deleted');
    }
}
