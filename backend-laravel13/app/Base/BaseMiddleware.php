<?php

declare(strict_types=1);

namespace App\Base;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base middleware/中间件基类
 *
 * Provides handle() template and guard() helper to detect which guard
 * (api / admin-api) the current request is authenticated against.
 * 提供 handle() 模板与 guard() 辅助方法，用于判定当前请求走的是 api 还是 admin-api 守卫。
 */
abstract class BaseMiddleware
{
    /**
     * Handle an incoming request/处理传入请求
     *
     * Subclasses implement business logic inside handle(); call $this->guard()
     * to detect the current guard.
     * 子类在 handle() 中实现业务逻辑；通过 $this->guard() 判定当前守卫。
     */
    abstract public function handle(Request $request, Closure $next): Response;

    /**
     * Detect the current request's guard/判定当前请求所属守卫
     *
     * Returns 'api' if the request is authenticated via the api guard,
     * 'admin-api' if via admin-api, null if unauthenticated on both.
     * 若请求通过 api 守卫认证返回 'api'，通过 admin-api 返回 'admin-api'，两端均未认证返回 null。
     */
    protected function guard(Request $request): ?string
    {
        if (auth('api')->check()) {
            return 'api';
        }
        if (auth('admin-api')->check()) {
            return 'admin-api';
        }

        return null;
    }
}
