<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUserStatusRequest;
use App\Services\Admin\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * C-end user management controller (admin side)/普通用户管理控制器（管理员视角）
 *
 * Handles user listing, detail, status toggle, online count and force kick
 * 处理用户列表、详情、启停、在线终端数与强制踢下线
 */
class UserController extends Controller
{
    public function __construct(private UserService $service)
    {
    }

    /**
     * Paginated C-end user list (route #42)/普通用户分页列表（路由 #42）
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
     * User detail (route #43)/用户详情（路由 #43）
     */
    public function show(int $id): JsonResponse
    {
        $user = $this->service->show($id);

        return message('api.success', 0, $user);
    }

    /**
     * Toggle user status (route #44)/启用禁用用户（路由 #44）
     */
    public function updateStatus(AdminUserStatusRequest $request, int $id): JsonResponse
    {
        $user = $this->service->updateStatus($id, (int) $request->validated('status'));

        return message('api.success', 0, $user);
    }

    /**
     * Online device count of a user (route #45)/用户在线终端数（路由 #45）
     */
    public function online(int $id): JsonResponse
    {
        $count = $this->service->online($id);

        return message('api.success', 0, ['count' => $count]);
    }

    /**
     * Force kick all devices of a user (route #46, high risk)/强制踢出用户全部终端（路由 #46，高危）
     */
    public function kick(int $id): JsonResponse
    {
        $this->service->kick($id);

        return message('api.device.kicked_all');
    }
}
