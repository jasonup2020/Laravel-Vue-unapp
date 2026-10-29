<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Support\CaptchaService;
use App\Support\SaltHasher;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * C-end user authentication service/C 端普通用户认证服务.
 */
class AuthService
{
    public function __construct(
        private MultiDeviceService $multiDevice,
        private CaptchaService $captcha,
    ) {
    }

    /**
     * Login: verify captcha → verify credentials → check status → issue token → register in device set/
     * 登录：先校验验证码 → 校验邮箱密码 → 状态检查 → 签发 token → 登记多端集合.
     *
     * @param array{email?: string, password?: string, captcha_id?: string, captcha_code?: string} $credentials
     * @return array{token: string, user: User}
     */
    public function login(array $credentials): array
    {
        // Captcha first (one-time) before credential check/认证前先校验验证码（一次性）
        $this->captcha->validate(
            $credentials['captcha_id'] ?? '',
            $credentials['captcha_code'] ?? ''
        );

        // Find user by email/按邮箱查询用户
        $user = User::query()
            ->where('email', $credentials['email'] ?? '')
            ->first();

        // User not found or password mismatch (Hash(明文+salt))/用户不存在或密码错误（Hash(明文+盐)）
        if (! $user || ! SaltHasher::check($credentials['password'] ?? '', $user->salt, (string) $user->password)) {
            throw new ApiException(ErrorCode::UNAUTHORIZED, 'api.login.invalid_credentials');
        }

        // Account disabled/账号被禁用（status 为 BackedEnum，取 value 比较）
        if ($user->status->value !== 1) {
            throw new ApiException(ErrorCode::FORBIDDEN, 'api.login.disabled');
        }

        // Issue JWT with guard=api claim/签发 JWT，带 guard=api 自定义声明
        $token = JWTAuth::claims(['guard' => 'api'])->fromUser($user);

        // Register in multi-device set/登记多端在线集合
        $this->multiDevice->register((int) $user->id, $token);

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    /**
     * Refresh token: capture old payload(sub) & old token first, then issue a new token,
     * and sync the device set (remove old, register new)/
     * 刷新 token：先取旧 payload(sub) 与旧 token，再换发新 token，同步维护多端集合（旧 remove、新 register）.
     */
    public function refresh(): string
    {
        // Timing: read old payload before refresh invalidates it/时序关键点：先解析旧 payload，refresh 后旧 token 会进黑名单
        $oldPayload = JWTAuth::parseToken()->getPayload();
        $oldUserId = (int) $oldPayload->get('sub');
        $oldToken = (string) JWTAuth::getToken();

        // Issue new token/换发新 token
        $newToken = JWTAuth::parseToken()->refresh();

        // Parse new token sub/解析新 token 的 sub
        $newPayload = JWTAuth::setToken($newToken)->getPayload();
        $newUserId = (int) $newPayload->get('sub');

        // Remove old, register new/旧设备移除 + 新设备登记
        $this->multiDevice->remove($oldUserId, $oldToken);
        $this->multiDevice->register($newUserId, $newToken);

        return $newToken;
    }

    /**
     * Logout: remove current token from device set and invalidate JWT/
     * 登出：从多端集合移除当前 token 并使 JWT 失效.
     */
    public function logout(): void
    {
        // Get current api-guarded user and token/取当前 api 守卫用户与 token
        $user = auth('api')->user();
        $token = (string) (auth('api')->getToken() ?? '');

        if ($user) {
            // Remove from set and blacklist/从集合移除并黑名单
            $this->multiDevice->remove((int) $user->id, $token);
        }

        // Invalidate JWT/使 JWT 失效（invalidate）
        auth('api')->logout();
    }
}
