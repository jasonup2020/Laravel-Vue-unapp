<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Enums\DictStatus;
use App\Models\Dict;
use Illuminate\Support\Facades\Cache;

/**
 * C-end dict service/C 端数据字典服务
 *
 * Hot path for 100k QPS: cache-aside with mutex rebuild to avoid cache breakdown
 * 10 万级 QPS 热路径：缓存旁路 + 互斥锁重建防击穿
 */
class DictService
{
    /** Dict type list cache TTL (24h)/字典类型列表缓存 TTL（24 小时） */
    public const CACHE_TTL = 86400;

    /** Max seconds to wait for the rebuild lock/等待重建锁最长秒数 */
    private const LOCK_WAIT_SECONDS = 3;

    /**
     * Get enabled dict items by type (cache-aside + mutex rebuild)/
     * 按类型获取启用字典项列表（缓存旁路 + 互斥重建）
     *
     * @param string $type Dict type key/字典类型标识
     * @return array<int, array<string, mixed>>
     */
    public function itemsByType(string $type): array
    {
        $key = "dict:type:{$type}";

        // Cache-aside read/旁路读缓存
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        // Mutex rebuild: only one process rebuilds, others wait up to LOCK_WAIT_SECONDS/
        // 互斥重建：仅一个进程重建，其余等待最多 LOCK_WAIT_SECONDS
        $result = Cache::lock("dict:lock:{$type}", 10)->block(
            self::LOCK_WAIT_SECONDS,
            function () use ($key, $type): array {
                // Double-check after acquiring lock/获锁后二次检查，避免重复重建
                $cached = Cache::get($key);
                if (is_array($cached)) {
                    return $cached;
                }

                return $this->remember($key, $type);
            }
        );

        // Lock wait timeout: fall back to direct DB read (never block the request)/
        // 锁等待超时：降级直查数据库，绝不让请求挂死
        if (!is_array($result)) {
            return $this->queryFromDb($type);
        }

        return $result;
    }

    /**
     * Query enabled items from DB and write cache/查库并回写缓存
     *
     * @param string $key  Cache key/缓存键
     * @param string $type  Dict type/字典类型
     * @return array<int, array<string, mixed>>
     */
    private function remember(string $key, string $type): array
    {
        $items = $this->queryFromDb($type);
        Cache::put($key, $items, self::CACHE_TTL);

        return $items;
    }

    /**
     * Query enabled dict items ordered by sort/查询启用字典项并按 sort 排序
     *
     * @param string $type Dict type/字典类型
     * @return array<int, array<string, mixed>>
     */
    private function queryFromDb(string $type): array
    {
        $rows = Dict::query()
            ->where('type', $type)
            ->where('status', DictStatus::ENABLED->value)
            ->orderBy('sort')
            ->orderBy('id') // stable order tie-breaker/排序稳定兜底
            ->get(['snow_id', 'type', 'label', 'label_en', 'value', 'sort'])
            ->toArray();

        // Transform label into bilingual object/将 label 转换为双语对象
        return array_map(function (array $row): array {
            $row['label'] = [
                'zh-CN' => $row['label'],
                'en' => $row['label_en'] ?? $row['label'],
            ];
            unset($row['label_en']);
            return $row;
        }, $rows);
    }
}
