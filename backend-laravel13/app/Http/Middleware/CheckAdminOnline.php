<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Base\BaseMiddleware;
use Closure;
use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin single sign-on check/管理员单点登录校验中间件
 *
 * Verifies that the current JWT token equals the admin's current online token.
 * Only one active session per admin: a newer login replaces the stored token,
 * so the older token is rejected with 401 (kicked).
 * 校验当前 JWT token 是否等于该管理员当前在线 token。每个管理员只允许一个有效会话：
 * 新登录覆盖存储的 token，旧 token 校验失败抛 401（被挤下线）。
 *
 * Middleware alias/中间件别名: check.admin.online
 */
class CheckAdminOnline extends BaseMiddleware
{
    /**
     * Handle an incoming request/处理请求
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('admin-api')->user();

        // Unauthenticated requests are left to auth:admin-api middleware
        // 未认证请求交由 auth:admin-api 中间件处理
        if (! $admin) {
            return $next($request);
        }

        // Strictly resolve the current token from the request header (see CheckUserOnline:
        // jwt-auth's JWTGuard caches the token on the singleton guard across requests).
        // 严格从当前请求头解析 token（同 CheckUserOnline：避免 guard 缓存旧 token 误判）。
        $token = (string) $request->bearerToken();

        // Single sign-on: the token must match the admin's current online token.
        // No record => the token was issued before SSO was enabled → kicked (re-login required).
        // 单点：token 必须等于该管理员当前在线 token。无记录说明为启用单点前签发的旧 token → 踢下线（需重新登录）。
        if (Cache::get('admin_online:'.$admin->id) !== $token) {
            throw new ApiException(ErrorCode::UNAUTHORIZED, 'api.account.kicked');
        }

        return $next($request);
    }
}
