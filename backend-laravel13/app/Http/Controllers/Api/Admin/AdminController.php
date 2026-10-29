<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminChangePasswordRequest;
use App\Http\Requests\Admin\AdminStatusRequest;
use App\Http\Requests\Admin\AdminStoreRequest;
use App\Http\Requests\Admin\AdminUpdateRequest;
use App\Http\Requests\Admin\ResetPasswordRequest;
use App\Services\Admin\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin management controller/管理员管理控制器
 *
 * Handles profile, password change, CRUD, status and role binding
 * 处理管理员资料、改密、CRUD、启停与角色绑定
 */
class AdminController extends Controller
{
    public function __construct(private AdminService $service)
    {
    }

    /**
     * Get current admin profile (route #22)/获取当前管理员本人信息（路由 #22）
     */
    public function profile(Request $request): JsonResponse
    {
        /** @var \App\Models\Admin $admin */
        $admin = $request->user('admin-api');
        $profile = $this->service->profile($admin->id);

        return message('api.success', 0, $profile);
    }

    /**
     * Change own password (route #23, high risk)/修改自身密码（路由 #23，高危）
     */
    public function changePassword(AdminChangePasswordRequest $request): JsonResponse
    {
        /** @var \App\Models\Admin $admin */
        $admin = $request->user('admin-api');
        $validated = $request->validated();

        $this->service->changePassword(
            $admin->id,
            $validated['old_password'],
            $validated['password']
        );

        return message('api.password.changed');
    }

    /**
     * Paginated admin list (route #36)/管理员分页列表（路由 #36）
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->service->index([
            'page' => (int) $request->query('page', 1),
            'page_size' => (int) $request->query('page_size', 15),
            'status' => $request->query('status'),
            'keyword' => $request->query('keyword'),
        ]);

        return message('api.success', 0, $result);
    }

    /**
     * Create admin and bind roles (route #37, high risk)/创建管理员并绑定角色（路由 #37，高危）
     */
    public function store(AdminStoreRequest $request): JsonResponse
    {
        $admin = $this->service->create($request->validated());

        return message('api.success', 0, $admin);
    }

    /**
     * Update admin (route #38, high risk)/更新管理员（路由 #38，高危）
     */
    public function update(AdminUpdateRequest $request, int $id): JsonResponse
    {
        $admin = $this->service->update($id, $request->validated());

        return message('api.success', 0, $admin);
    }

    /**
     * Reset another admin's password (route #39, high risk)/重置管理员密码（路由 #39，高危）
     */
    public function resetPassword(ResetPasswordRequest $request, int $id): JsonResponse
    {
        $this->service->resetPassword($id, $request->validated('password'));

        return message('api.password.changed');
    }

    /**
     * Toggle admin status (route #40)/启用禁用管理员（路由 #40）
     */
    public function updateStatus(AdminStatusRequest $request, int $id): JsonResponse
    {
        $admin = $this->service->updateStatus($id, (int) $request->validated('status'));

        return message('api.success', 0, $admin);
    }

    /**
     * Soft delete admin (route #41, high risk)/软删除管理员（路由 #41，高危）
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->destroy($id);

        return message('api.success');
    }
}
