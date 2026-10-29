<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Base\BaseMiddleware;
use Closure;
use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Services\User\MultiDeviceService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * C-end multi-device online check/C 端多端在线校验中间件
 *
 * Mounts on authenticated C-end (api guard) routes. Verifies that the current
 * JWT token is still in the user's online token set; if not (kicked by admin
 * or replaced after exceeding the 3-device limit), rejects with 401.
 * Extends BaseMiddleware.
 * 仅作用于 C 端 api 守卫鉴权路由。校验当前 JWT token 是否仍在用户在线 token 集合中；
 * 若不在（被管理员踢下线或超过 3 端上限被替换），抛 401。
 * 继承 BaseMiddleware。
 *
 * Middleware alias/中间件别名: check.user.online
 */
class CheckUserOnline extends BaseMiddleware
{
    /**
     * Constructor/构造函数
     *
     * @param  MultiDeviceService $multiDevice  multi-device registry service/多端登录注册服务
     */
    public function __construct(private MultiDeviceService $multiDevice)
    {
    }

    /**
     * Handle an incoming request/处理请求
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        // Unauthenticated requests are left to auth:api middleware/未认证请求交由 auth:api 中间件处理
        if (! $user) {
            return $next($request);
        }

        // Resolve current token strictly from the request header.
        // Do NOT use auth('api')->getToken(): jwt-auth's JWTGuard caches the token
        // on the singleton guard across requests within the same app lifecycle
        // (e.g. Pest tests), so a stale token parsed on an earlier request would
        // leak into later requests and falsely trigger 401.
        // 严格从当前请求头解析 token。
        // 不使用 auth('api')->getToken()：jwt-auth 的 JWTGuard 在同一 app 实例内跨请求
        // 缓存 $token（如 Pest 测试场景），首个请求解析出的旧 token 会泄漏到后续请求，
        // 导致误判 401。
        $token = (string) $request->bearerToken();

        // Token not in online set: account has been kicked/当前 token 不在在线集合：账号已被踢下线
        if (! $this->multiDevice->isOnline((int) $user->id, $token)) {
            throw new ApiException(ErrorCode::UNAUTHORIZED, 'api.account.kicked');
        }

        return $next($request);
    }
}
