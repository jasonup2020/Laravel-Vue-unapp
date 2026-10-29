<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\User;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\LoginRequest;
use App\Services\User\AuthService;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

/**
 * C-end user authentication controller/C 端普通用户认证控制器.
 * Routes mounted by S16: login/refresh without guard; logout with auth:api + check.user.online/
 * 路由由 S16 挂载：login/refresh 无守卫；logout 挂 auth:api + check.user.online.
 */
class AuthController extends Controller
{
    public function __construct(private AuthService $auth)
    {
    }

    /**
     * User login/普通用户登录.
     * Returns token, expires_in (seconds) and snow-encoded user/
     * 返回 token、expires_in（秒）与 snow 化用户信息.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Delegate credential check and token issuance/调 Service 完成凭证校验与签发
        $result = $this->auth->login($request->validated());

        return message('api.login.success', 0, [
            'token' => $result['token'],
            'expires_in' => (int) config('jwt.ttl', 60) * 60,
            // Model hides id/password, JSON includes snow_id/模型已隐藏 id/password，JSON 含 snow_id
            'user' => $result['user']->toArray(),
        ]);
    }

    /**
     * Refresh user token/刷新普通用户 token.
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = $this->auth->refresh();
        } catch (JWTException $e) {
            // Invalid or expired old token/旧 token 无效/过期
            throw new ApiException(ErrorCode::UNAUTHORIZED, 'api.token_invalid');
        }

        return message('api.refresh.success', 0, [
            'token' => $token,
            'expires_in' => (int) config('jwt.ttl', 60) * 60,
        ]);
    }

    /**
     * User logout/普通用户登出.
     */
    public function logout(): JsonResponse
    {
        // Remove from device set and invalidate JWT/从多端集合移除并使 JWT 失效
        $this->auth->logout();

        return message('api.logout.success');
    }
}
