<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\StatusCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Global exception renderer/全局异常统一渲染器
 *
 * Registered via bootstrap/app.php withExceptions->renderable as a static callback/
 * 由 bootstrap/app.php 的 withExceptions->renderable 注册为静态回调：
 *   ->renderable(fn (Throwable $e, Request $r) => ApiExceptionHandler::render($e, $r))
 *
 * Contract/约定：
 * - HTTP status is ALWAYS 200; real result is carried by body `code`/
 *   HTTP 状态恒为 200，真实结果由 body `code` 表达
 * - Body shape: {success:false, code, msg, data}/
 *   所有异常均输出 message() 结构：{success:false, code, msg, data}
 * - Production (APP_DEBUG=false) never leaks stack traces to client/
 *   生产环境（APP_DEBUG=false）不向客户端暴露堆栈
 *
 * Mapping/错误码映射：
 *   AuthenticationException      → 1002 api.unauthorized
 *   AuthorizationException       → 1003 api.forbidden
 *   ValidationException          → 1005 api.validation_error (data=field errors)
 *   ModelNotFoundException       → 1004 api.not_found
 *   NotFoundHttpException        → 1004 api.not_found
 *   HttpExceptionInterface 429   → 1006 api.rate_limited
 *   HttpExceptionInterface 4xx   → 1002/1003/1004/1007/1005 by status
 *   ApiException (ErrorCode)     → mapped via ErrorCode::toStatusCode()
 *   Other Throwable              → 5000 api.server_error (production)
 */
class ApiExceptionHandler
{
    public static function render(Throwable $e, Request $request): JsonResponse
    {
        // 1) Business exception: map its ErrorCode to StatusCode/
        //    业务异常：将 ErrorCode 映射为 StatusCode
        if ($e instanceof ApiException) {
            $code = $e->errorCode->toStatusCode();

            return message($e->getMessage(), $code->value, $e->data ?: null);
        }

        // 2) Validation failure: 1005 with field error details/
        //    表单校验失败：1005，data 字段错误明细
        if ($e instanceof ValidationException) {
            return message(
                'api.validation_error',
                StatusCode::VALIDATION->value,
                $e->errors()
            );
        }

        // 3) Unauthenticated: 1002/未认证：1002
        if ($e instanceof AuthenticationException) {
            return message('api.unauthorized', StatusCode::UNAUTHORIZED->value, null);
        }

        // 4) RBAC authorization denied: 1003/RBAC 无权：1003
        if ($e instanceof AuthorizationException) {
            return message('api.forbidden', StatusCode::FORBIDDEN->value, null);
        }

        // 5) Model not found (implicit route binding): 1004/
        //    模型未找到（隐式路由绑定）：1004
        if ($e instanceof ModelNotFoundException) {
            return message('api.not_found', StatusCode::NOT_FOUND->value, null);
        }

        // 6) Route not found: 1004/路由未找到：1004
        if ($e instanceof NotFoundHttpException) {
            return message('api.not_found', StatusCode::NOT_FOUND->value, null);
        }

        // 7) Other HTTP exceptions (e.g. 429 rate limit, 403 denied)/
        //    其他 HTTP 异常（如 429 限流、403 拒绝）
        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $code = match ($status) {
                401 => StatusCode::UNAUTHORIZED,
                403 => StatusCode::FORBIDDEN,
                404 => StatusCode::NOT_FOUND,
                409 => StatusCode::CONFLICT,
                422 => StatusCode::VALIDATION,
                429 => StatusCode::RATE_LIMITED,
                default => StatusCode::INTERNAL,
            };

            return message($code->label(), $code->value, null);
        }

        // 8) Any other throwable: always log; production returns generic 5000/
        //    其余未捕获异常：一律记录日志；生产输出通用 5000
        Log::error('Unhandled API exception', [
            'message' => $e->getMessage(),
            'class'   => get_class($e),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'uri'     => $request->getRequestUri(),
            'method'  => $request->method(),
        ]);

        if ((bool) config('app.debug', false)) {
            // Local debug: return exception details for troubleshooting/
            // 本地调试：返回异常明细，便于定位
            return message($e->getMessage(), StatusCode::INTERNAL->value, [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => explode("\n", $e->getTraceAsString()),
            ]);
        }

        return message('api.server_error', StatusCode::INTERNAL->value, null);
    }
}
