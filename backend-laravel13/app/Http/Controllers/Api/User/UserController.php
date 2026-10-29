<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ChangePasswordRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Services\User\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * C-end user profile controller/C 端个人中心控制器.
 * Routes: GET /api/me, PUT /api/me, PUT /api/me/password/路由.
 * Middleware: auth:api + check.user.online (mounted by S16)/中间件：由 S16 挂载.
 */
class UserController extends Controller
{
    public function __construct(private UserService $userService)
    {
    }

    /**
     * Get current logged-in user profile/获取当前登录用户个人信息.
     */
    public function profile(Request $request): JsonResponse
    {
        // Resolve current user id via api guard/从 api 守卫解析当前用户 ID
        $user = $this->userService->profile((int) $request->user('api')->id);

        // data 直接为用户字段（含 snow_id，不含 id/password）/data is user fields directly
        return message('api.success', 0, $user->toArray());
    }

    /**
     * Update current user profile (name only)/更新当前用户资料（仅允许 name）.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        // Delegate to service/调 Service 更新资料
        $user = $this->userService->updateProfile(
            (int) $request->user('api')->id,
            $request->validated()
        );

        // data 直接为用户字段 /data is user fields directly
        return message('api.profile.updated', 0, $user->toArray());
    }

    /**
     * Change current user password/修改当前用户密码.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        // Delegate old-password check and update/调 Service 校验原密码并更新
        $this->userService->changePassword(
            (int) $request->user('api')->id,
            (string) $request->input('old_password'),
            (string) $request->input('password')
        );

        return message('api.password.changed');
    }
}
