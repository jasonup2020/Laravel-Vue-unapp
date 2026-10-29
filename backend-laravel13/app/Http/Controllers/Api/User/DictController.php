<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Services\User\DictService;
use Illuminate\Http\JsonResponse;

/**
 * C-end dict controller/C 端数据字典控制器
 *
 * Get enabled option list by type, served via Redis cache-aside
 * 按类型获取启用选项列表，走 Redis 缓存旁路
 */
class DictController extends Controller
{
    /**
     * Show enabled dict items by type/按 type 获取启用字典项
     *
     * @param string    $type  Dict type key/字典类型标识
     * @param DictService $service Dict service/字典服务
     */
    public function show(string $type, DictService $service): JsonResponse
    {
        $items = $service->itemsByType($type);

        return message('api.success', 0, ['list' => $items]);
    }
}
