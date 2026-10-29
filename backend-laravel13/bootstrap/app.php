<?php

use App\Exceptions\ApiExceptionHandler;
use App\Http\Middleware\CheckAdminOnline;
use App\Http\Middleware\CheckUserOnline;
use App\Http\Middleware\CompositeRateLimit;
use App\Http\Middleware\IdempotencyRequest;
use App\Http\Middleware\AdminOperationLog;
use App\Http\Middleware\AdminPermission;
use App\Http\Middleware\IpBlacklist;
use App\Http\Middleware\LangSwitch;
use App\Http\Middleware\ResponseDesensitize;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // HandleCors is already part of Laravel 13's default global middleware
        // stack (reads config/cors.php), so it is NOT prepended here again.

        // Global prepend: IpBlacklist outermost (block bad IPs early), then
        // LangSwitch. prepend() puts middleware at the front; the last
        // prepended runs first, so LangSwitch is prepended after IpBlacklist
        // to keep IpBlacklist outermost.
        $middleware->prepend(IpBlacklist::class);
        $middleware->prepend(LangSwitch::class);

        // Route middleware aliases (contract section 4, all 8).
        $middleware->alias([
            'lang' => LangSwitch::class,
            'ip.blacklist' => IpBlacklist::class,
            'api.composite.rate' => CompositeRateLimit::class,
            'idempotency' => IdempotencyRequest::class,
            'response.desensitize' => ResponseDesensitize::class,
            'check.user.online' => CheckUserOnline::class,
            'check.admin.online' => CheckAdminOnline::class,
            'admin.permission' => AdminPermission::class,
            'admin.operation.log' => AdminOperationLog::class,
        ]);

        // Trust all proxies (Nginx terminates SSL / real IP via X-Forwarded-For).
        $middleware->trustProxies(at: '*');

        // API is stateless JSON: never redirect unauthenticated users to a
        // named "login" route (which does not exist in this API-only app).
        // Returning null lets AuthenticationException flow into the unified
        // exception handler which outputs {success:false, code:1002, ...}.
        // API 为无状态 JSON：未认证用户绝不重定向到不存在的 login 命名路由；
        // 返回 null 让 AuthenticationException 走统一异常处理器输出 1002。
        Authenticate::redirectUsing(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Delegate API/JSON exception rendering to the unified handler.
        // Non-API / non-JSON requests fall through to the framework default.
        $exceptions->renderable(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiExceptionHandler::render($e, $request);
            }

            return null;
        });
    })->create();
