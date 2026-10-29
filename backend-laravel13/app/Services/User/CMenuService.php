<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Models\CMenu;
use Illuminate\Support\Facades\Cache;

/**
 * C-end public menu service/C端公开菜单服务
 *
 * Returns visible C-end menus via Redis cache (key: c_menu:list, TTL 600s)
 * 通过 Redis 缓存返回可见的 C 端菜单（key: c_menu:list，TTL 600 秒）
 */
class CMenuService
{
    /** Cache TTL in seconds/缓存 TTL 秒数 */
    private const CACHE_TTL = 600;

    /** Cache key/缓存键 */
    private const CACHE_KEY = 'c_menu:list';

    /**
     * Get visible C-end menu list (cached)/获取可见 C 端菜单列表（走缓存）
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            $menus = CMenu::query()
                ->where('is_show', true)
                ->orderBy('sort')
                ->orderBy('id')
                ->get();

            return $menus->map(fn (CMenu $m) => $this->format($m))->all();
        });
    }

    /**
     * Format a CMenu model into array with bilingual title/
     * 格式化 CMenu 模型为含双语 title 的数组
     *
     * @return array<string, mixed>
     */
    private function format(CMenu $menu): array
    {
        $arr = $menu->toArray();
        $arr['title'] = [
            'zh-CN' => $menu->title,
            'en' => $menu->title_en ?? $menu->title,
        ];
        unset($arr['title_en'], $arr['is_external']);

        return $arr;
    }
}
