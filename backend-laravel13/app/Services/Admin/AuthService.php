<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Services\Support\CaptchaService;
use App\Support\SaltHasher;
use Illuminate\Support\Facades\Cache;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * Admin authentication service (admin-api guard)/管理员认证服务（admin-api 守卫）.
 * Note: admins are not bound by the 3-device limit and do not use MultiDeviceService/
 * 注意：管理员不受多端 3 端限制，不调用 MultiDeviceService.
 */
class AuthService
{
    public function __construct(private CaptchaService $captcha)
    {
    }

    /**
     * Admin login: verify captcha → credentials → check status → issue token (claims guard=admin-api)/
     * 管理员登录：先校验验证码 → 校验用户名密码 → 状态检查 → 签发 token（claims guard=admin-api）.
     *
     * @param array{username?: string, password?: string, captcha_id?: string, captcha_code?: string} $credentials
     * @return array{token: string, user: Admin}
     */
    public function login(array $credentials): array
    {
        // Captcha first (one-time) before credential check/认证前先校验验证码（一次性）
        $this->captcha->validate(
            $credentials['captcha_id'] ?? '',
            $credentials['captcha_code'] ?? ''
        );

        // Find admin by username/按用户名查询管理员
        $admin = Admin::query()
            ->where('username', $credentials['username'] ?? '')
            ->first();

        // Invalid credentials (Hash(明文+salt))/凭证错误（Hash(明文+盐)）
        if (! $admin || ! SaltHasher::check($credentials['password'] ?? '', $admin->salt, (string) $admin->password)) {
            throw new ApiException(ErrorCode::UNAUTHORIZED, 'api.login.invalid_credentials');
        }

        // Admin disabled/管理员被禁用（status 为 BackedEnum，取 value 比较）
        if ($admin->status->value !== 1) {
            throw new ApiException(ErrorCode::FORBIDDEN, 'api.login.disabled');
        }

        // Issue JWT with guard=admin-api claim/签发 JWT，带 guard=admin-api 声明
        $token = JWTAuth::claims(['guard' => 'admin-api'])->fromUser($admin);

        // Single sign-on: record the only active token (TTL matches JWT ttl).
        // 单点登录：记录当前唯一在线 token（TTL 与 JWT 有效期一致），新登录覆盖旧值使旧 token 失效。
        Cache::put('admin_online:'.$admin->id, $token, 60 * 60);

        return [
            'token' => $token,
            'user' => $admin,
        ];
    }

    /**
     * Refresh admin token/刷新管理员 token.
     */
    public function refresh(): string
    {
        return JWTAuth::parseToken()->refresh();
    }

    /**
     * Admin logout: clear the online token and invalidate current JWT/
     * 管理员登出：清除在线 token 并使当前 JWT 失效.
     */
    public function logout(): void
    {
        $admin = auth('admin-api')->user();
        if ($admin) {
            Cache::forget('admin_online:'.$admin->id);
        }
        auth('admin-api')->logout();
    }
}
