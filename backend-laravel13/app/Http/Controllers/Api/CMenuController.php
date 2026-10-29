<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\User\CMenuService;
use Illuminate\Http\JsonResponse;

/**
 * C-end public menu controller/C端公开菜单控制器
 *
 * Returns visible C-end menus without authentication
 * 无需鉴权即可获取可见的 C 端菜单
 */
class CMenuController extends Controller
{
    public function __construct(private CMenuService $service)
    {
    }

    /**
     * List visible C-end menus (public, no auth)/列出可见 C 端菜单（公开无鉴权）
     */
    public function index(): JsonResponse
    {
        $list = $this->service->list();

        return message('api.success', 0, ['list' => $list]);
    }
}
