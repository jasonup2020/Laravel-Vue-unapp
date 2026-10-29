<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Base\BaseMiddleware;
use Closure;
use App\Exceptions\ApiException;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin RBAC permission middleware/管理员 RBAC 权限中间件
 *
 * Validates whether the currently authenticated admin (via admin-api guard)
 * owns the required permission code. Permission codes are cached as a plain
 * PHP array in the `admin:permissions:{adminId}` key for 600s. We MUST NOT cache
 * an Illuminate\Support\Collection instance: serializing a Collection into
 * Redis and unserializing it in a later request (when framework classes are
 * not yet autoloaded) yields __PHP_Incomplete_Class and triggers fatal errors
 * on ->contains(). A plain array avoids this trap entirely.
 * Super-admin role bypasses the check (encoded as wildcard '*' inside the
 * cached array). Throws 403 otherwise.
 * 校验当前 admin-api 守卫认证的管理员是否拥有指定权限码。
 * 权限码以纯 PHP 数组形式缓存于 `admin:permissions:{adminId}`，TTL 600 秒。
 * 禁止缓存 Illuminate\Support\Collection 实例：Collection 序列化进 Redis 后，
 * 后续请求反序列化时若框架类尚未 autoload 会得到 __PHP_Incomplete_Class，
 * 调用 ->contains() 直接 fatal。使用纯数组彻底规避该陷阱。
 * super-admin 角色直接放行（缓存数组内含通配符 `*`），否则抛 403。
 * 继承 BaseMiddleware；所需权限码通过可变尾部参数传入，以保持与抽象
 * handle(Request, Closure) 模板的 LSP 兼容。
 *
 * Middleware alias/中间件别名: admin.permission:{code}
 */
class AdminPermission extends BaseMiddleware
{
    /**
     * Handle an incoming request/处理请求
     *
     * @param  string ...$codes  variadic middleware params; first entry is the required permission code
     *                          可变参数；首个为所需权限码
     */
    public function handle(Request $request, Closure $next, string|int ...$codes): Response
    {
        // Required permission code is the first variadic arg/所需权限码为首个可变参数
        $code = (string) ($codes[0] ?? '');

        // Current admin id from admin-api guard/从 admin-api 守卫取当前管理员 id
        $adminId = (int) auth('admin-api')->id();

        // Cache permission codes as a PLAIN ARRAY for 600s.
        // Super-admin is encoded as ['*'] so one cache hit covers both bypass and membership.
        // Do NOT return a Collection here — see class docblock for the serialization trap.
        // 权限码以纯数组形式缓存 600 秒；super-admin 编码为 ['*']，一次缓存命中同时解决超管放行与成员判定。
        // 禁止返回 Collection——类 docblock 已说明序列化陷阱。
        $permissions = Cache::remember(
            "admin:permissions:{$adminId}",
            600,
            function () use ($adminId): array {
                /** @var Admin|null $admin */
                // Eager-load roles.permissions to avoid N+1/预加载 roles.permissions 避免 N+1
                $admin = Admin::query()->with('roles.permissions')->find($adminId);
                if (! $admin) {
                    return [];
                }

                // Super-admin: bypass all permission checks/超管：绕过全部权限校验
                if ($admin->roles->contains('code', 'super-admin')) {
                    return ['*'];
                }

                // Union of permission codes across all assigned roles; materialize to plain array
                // 合并全部角色的权限码并集；物化回纯数组
                return $admin->roles
                    ->pluck('permissions')
                    ->flatten()
                    ->pluck('code')
                    ->filter()
                    ->values()
                    ->all();
            }
        );

        // Normalize just in case a stale cache entry still holds a legacy Collection/兜底归一化：防止历史脏缓存残留 Collection
        if (! is_array($permissions)) {
            $permissions = is_iterable($permissions) ? iterator_to_array($permissions) : [];
        }

        // Pass when wildcard (super-admin) or the required code is present/通配符或命中权限码即放行
        if (in_array('*', $permissions, true) || in_array($code, $permissions, true)) {
            return $next($request);
        }

        // No permission: reject with 403/无权限：抛 403
        throw ApiException::forbidden('api.forbidden');
    }
}
