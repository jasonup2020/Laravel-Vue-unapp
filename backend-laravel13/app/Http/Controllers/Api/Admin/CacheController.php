<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use App\Base\BaseController;

/**
 * Cache management / 缓存管理控制器
 *
 * Provides admin operations to flush application caches so that frontends
 * (management & C-end) always read the latest data without restarting services.
 * Covers locale pack, menu tree, dict, C-end menu list, rate-limit rules, etc.
 * 提供管理端清空应用缓存的接口，使管理端与 C 端前端无需重启服务即可读取最新数据。
 * 覆盖范围：语言包、菜单树、字典、C 端菜单列表、限流规则等。
 */
class CacheController extends BaseController
{
    /**
     * Flush all application caches / 清空全部应用缓存.
     *
     * Any logged-in admin can trigger it (guarded by auth:admin-api); it flushes
     * every cache key (locale, menu, dict, c_menu, rate rules...) at once.
     * 任意已登录管理员均可触发（受 auth:admin-api 守卫保护）；一次性清空全部缓存键。
     *
     * @return JsonResponse
     */
    public function flush(): JsonResponse
    {
        Cache::flush();

        return $this->success(null, 'api.success');
    }
}
