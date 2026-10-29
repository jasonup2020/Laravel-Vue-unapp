<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Services\Admin\AuthService;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

/**
 * Admin authentication controller (admin-api guard)/管理员认证控制器（admin-api 守卫）.
 * Routes mounted by S16/路由由 S16 挂载.
 */
class AuthController extends Controller
{
    public function __construct(private AuthService $auth)
    {
    }

    /**
     * Admin login/管理员登录.
     */
    public function login(AdminLoginRequest $request): JsonResponse
    {
        // Delegate credential check and token issuance/调 Service 校验用户名密码并签发 token
        $result = $this->auth->login($request->validated());

        return message('api.login.success', 0, [
            'token' => $result['token'],
            'expires_in' => (int) config('jwt.ttl', 60) * 60,
            'user' => $result['user']->toArray(),
        ]);
    }

    /**
     * Refresh admin token/刷新管理员 token.
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = $this->auth->refresh();
        } catch (JWTException $e) {
            // Invalid or expired token/token 无效/过期
            throw new ApiException(ErrorCode::UNAUTHORIZED, 'api.token_invalid');
        }

        return message('api.refresh.success', 0, [
            'token' => $token,
            'expires_in' => (int) config('jwt.ttl', 60) * 60,
        ]);
    }

    /**
     * Admin logout/管理员登出.
     */
    public function logout(): JsonResponse
    {
        $this->auth->logout();

        return message('api.logout.success');
    }
}
