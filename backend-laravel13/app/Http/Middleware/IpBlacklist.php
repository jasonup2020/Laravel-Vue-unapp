<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Base\BaseMiddleware;
use Closure;
use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\IpBlacklist as IpBlacklistModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * IP blacklist middleware/IP 黑名单中间件
 *
 * Mounted as an outermost global middleware. The list of active blocked IPs is
 * cached for 300s; entries with expire_at = NULL (permanent) or expire_at > now
 * are considered active. Blocked clients receive a 403.
 * Extends BaseMiddleware.
 * 作为最外层全局中间件挂载。生效中的封禁 IP 列表缓存 300 秒；
 * expire_at 为 NULL（永久）或晚于当前时间视为生效。命中封禁的客户端返回 403。
 * 继承 BaseMiddleware。
 *
 * Middleware alias/中间件别名: ip.blacklist
 */
class IpBlacklist extends BaseMiddleware
{
    /**
     * Handle an incoming request/处理请求
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Cache active blacklist for 300s; expired rows pass through (purge handled by scheduled task)/生效黑名单缓存 300 秒；已过期行视为放行（清理由定时任务负责）
        $active = Cache::remember('ip_blacklist:active', 300, function () {
            return IpBlacklistModel::query()
                ->where(function ($q) {
                    // Permanent ban (expire_at IS NULL) OR still within ban window
                    // 永久封禁（expire_at IS NULL）或仍在封禁有效期内
                    $q->whereNull('expire_at')->orWhere('expire_at', '>', now());
                })
                ->pluck('ip')
                ->all();
        });

        // Client IP on the blacklist: reject with 403/客户端 IP 命中黑名单：抛 403
        if (in_array(client_ip(), (array) $active, true)) {
            throw new ApiException(ErrorCode::FORBIDDEN, 'api.ip.blocked');
        }

        return $next($request);
    }
}
