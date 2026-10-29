<?php

declare(strict_types=1);

namespace App\Base;

use App\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * Base controller/控制器基类
 *
 * Provides unified response shortcuts, pagination parsing and current-guard user lookup.
 * 提供统一响应便捷方法、分页参数解析与当前守卫用户获取。
 */
abstract class BaseController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Maximum allowed page size/分页大小上限
     */
    protected const int MAX_PAGE_SIZE = 100;

    /**
     * Default page size/默认分页大小
     */
    protected const int DEFAULT_PAGE_SIZE = 15;

    /**
     * Return a success JSON response/返回成功 JSON 响应
     *
     * @param  mixed  $data  payload/响应数据
     * @param  string $msg   i18n key or plain text/多语言 key 或直接文本
     * @param  int    $code  business code (default 0 = success)/业务码（默认 0 = 成功）
     */
    protected function success(mixed $data = null, string $msg = 'api.success', int $code = 0): JsonResponse
    {
        return message($msg, $code, $data);
    }

    /**
     * Return an error JSON response/返回失败 JSON 响应
     *
     * Thin wrapper around message() for failure cases; business code should prefer
     * throwing ApiException so the global handler can render it uniformly.
     * 失败场景的轻量封装；业务代码更推荐直接 throw ApiException，由全局异常处理器统一渲染。
     */
    protected function error(string $msg = 'api.common.biz_error', int $code = 1, mixed $data = null): JsonResponse
    {
        return message($msg, $code, $data);
    }

    /**
     * Parse page / page_size from request/解析请求中的 page / page_size
     *
     * Clamps page_size to MAX_PAGE_SIZE (100) to prevent oversized pagination.
     * 将 page_size 钳制到 MAX_PAGE_SIZE（100），防止超大分页。
     *
     * @return array{0:int,1:int}  [page, pageSize]
     */
    protected function parsePageParams(Request $request): array
    {
        $page = (int) $request->integer('page', 1);
        $pageSize = (int) $request->integer('page_size', self::DEFAULT_PAGE_SIZE);

        if ($page < 1) {
            $page = 1;
        }
        if ($pageSize < 1) {
            $pageSize = self::DEFAULT_PAGE_SIZE;
        }
        if ($pageSize > self::MAX_PAGE_SIZE) {
            $pageSize = self::MAX_PAGE_SIZE;
        }

        return [$page, $pageSize];
    }

    /**
     * Resolve the authenticated user for a given guard/获取指定守卫的已认证用户
     *
     * Returns null when the guard has no authenticated user (e.g. no/invalid token),
     * so callers can branch without raising.
     * 守卫未认证（无 token 或 token 无效）时返回 null，便于调用方分支处理而不抛异常。
     *
     * @param  string  $guard  e.g. api / admin-api/守卫名（如 api / admin-api）
     */
    protected function guardUser(string $guard): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        return auth($guard)->user();
    }

    /**
     * Get validated data from the current FormRequest/获取当前 FormRequest 的校验数据
     *
     * Reads $request->validated(); useful inside controllers that receive a typed
     * FormRequest and want a shortcut to the validated array.
     * 读取 $request->validated()；用于接收 FormRequest 的控制器快速取校验后数组。
     *
     * @return array<string,mixed>
     */
    protected function validatedData(Request $request): array
    {
        return $request->validated();
    }

    /**
     * Throw a 404 not found ApiException/抛出 404 资源不存在异常
     *
     * @throws ApiException
     */
    protected function notFound(string $msg = 'api.not_found'): never
    {
        throw ApiException::notFound($msg);
    }
}
