<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleAssignMenusRequest;
use App\Http\Requests\Admin\RoleAssignPermissionsRequest;
use App\Http\Requests\Admin\RoleStoreRequest;
use App\Http\Requests\Admin\RoleUpdateRequest;
use App\Services\Admin\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Role management controller/角色管理控制器
 *
 * Handles role CRUD, permission assignment and menu assignment
 * 处理角色 CRUD、权限分配与菜单分配
 */
class RoleController extends Controller
{
    public function __construct(private RoleService $service)
    {
    }

    /**
     * Paginated role list (route #30)/角色分页列表（路由 #30）
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->service->index([
            'page' => (int) $request->query('page', 1),
            'page_size' => (int) $request->query('page_size', 15),
        ]);

        return message('api.success', 0, $result);
    }

    /**
     * Create role (route #31, high risk)/创建角色（路由 #31，高危）
     */
    public function store(RoleStoreRequest $request): JsonResponse
    {
        $role = $this->service->create($request->validated());

        return message('api.role.saved', 0, $role);
    }

    /**
     * Update role (route #32, high risk)/更新角色（路由 #32，高危）
     */
    public function update(RoleUpdateRequest $request, int $id): JsonResponse
    {
        $role = $this->service->update($id, $request->validated());

        return message('api.role.updated', 0, $role);
    }

    /**
     * Delete role (route #33, high risk)/删除角色（路由 #33，高危）
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->destroy($id);

        return message('api.role.deleted');
    }

    /**
     * Assign permissions to role (route #34)/角色分配权限（路由 #34）
     */
    public function assignPermissions(RoleAssignPermissionsRequest $request, int $id): JsonResponse
    {
        $this->service->assignPermissions($id, $request->validated('permission_ids'));

        return message('api.role.assigned');
    }

    /**
     * Assign menus to role (route #35)/角色分配菜单（路由 #35）
     */
    public function assignMenus(RoleAssignMenusRequest $request, int $id): JsonResponse
    {
        $this->service->assignMenus($id, $request->validated('menu_ids'));

        return message('api.role.assigned');
    }
}
