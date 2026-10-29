<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PermissionStoreRequest;
use App\Http\Requests\Admin\PermissionUpdateRequest;
use App\Services\Admin\PermissionService;
use Illuminate\Http\JsonResponse;

/**
 * Permission controller/权限控制器
 *
 * Handles action-level permission listing, creation, update and deletion
 * 处理动作级权限的列出、新建、修改与删除
 */
class PermissionController extends Controller
{
    public function __construct(private PermissionService $service)
    {
    }

    /**
     * List all permissions grouped by group (route #29)/按 group 分组列出全部权限（路由 #29）
     */
    public function index(): JsonResponse
    {
        $result = $this->service->index();

        return message('api.permission.list', 0, $result);
    }

    /**
     * Create a custom permission (route #69)/新建自定义权限（路由 #69）
     */
    public function store(PermissionStoreRequest $request): JsonResponse
    {
        $permission = $this->service->create($request->validated());

        return message('api.success', 0, $permission);
    }

    /**
     * Update a permission (route #70)/修改权限（路由 #70）
     */
    public function update(PermissionUpdateRequest $request, int $id): JsonResponse
    {
        $permission = $this->service->update($id, $request->validated());

        return message('api.success', 0, $permission);
    }

    /**
     * Delete a custom permission (route #71)/删除自定义权限（路由 #71）
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return message('api.success');
    }
}
