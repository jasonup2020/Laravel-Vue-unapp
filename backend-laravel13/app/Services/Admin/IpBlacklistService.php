<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Exceptions\ApiException;
use App\Models\IpBlacklist;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * IP blacklist management service/IP 黑名单管理服务
 *
 * Admin-side CRUD for the ip_blacklist table. Every write invalidates the
 * 'ip_blacklist:active' cache key (TTL 300 read by the outermost IpBlacklist
 * middleware) so changes take effect immediately.
 * 管理端 ip_blacklist 表 CRUD。每次写操作失效 'ip_blacklist:active' 缓存键
 * （TTL 300，由最外层 IpBlacklist 中间件读取），使变更立即生效。
 */
class IpBlacklistService
{
    /**
     * Cache key of the active blocked-IP list shared with the middleware/
     * 与中间件共享的生效封禁 IP 列表缓存键
     */
    private const CACHE_KEY = 'ip_blacklist:active';

    /**
     * Paginate blacklist rows, optionally fuzzy-filtered by IP/分页查询黑名单，可按 IP 模糊过滤
     *
     * @param  int         $page     page number/页码
     * @param  int         $pageSize page size capped at 100/每页条数，上限 100
     * @param  string|null $ip      optional fuzzy IP filter/可选 IP 模糊过滤
     * @return array{list: array<int, IpBlacklist>, total: int, page: int, page_size: int}
     */
    public function paginate(int $page, int $pageSize, ?string $ip = null): array
    {
        $query = IpBlacklist::query()->orderByDesc('id');

        if ($ip !== null && $ip !== '') {
            // Fuzzy match on the ip column/按 ip 列模糊匹配
            $query->where('ip', 'like', '%' . $ip . '%');
        }

        return $this->formatPage(
            $query->paginate($pageSize, ['*'], 'page', $page),
            $page,
            $pageSize
        );
    }

    /**
     * Create a blacklist row/新增封禁记录
     *
     * Rejects duplicates among ACTIVE rows only (permanent = expire_at IS NULL,
     * or expire_at in the future); an expired row may be re-banned.
     * 仅拒绝生效中行的重复（永久 = expire_at IS NULL，或未过期）；
     * 已过期行允许重新封禁。
     *
     * @param  array{ip: string, remark?: string|null, expire_at?: string|null} $data validated input/校验通过的入参
     */
    public function store(array $data): IpBlacklist
    {
        // Active-duplicate check: permanent or still-valid rows/生效中重复校验：永久或未过期行
        $duplicate = IpBlacklist::query()
            ->where('ip', $data['ip'])
            ->where(function ($q): void {
                $q->whereNull('expire_at')->orWhere('expire_at', '>', now());
            })
            ->exists();

        if ($duplicate) {
            throw ApiException::business('api.ip.exists');
        }

        $model = IpBlacklist::create([
            'ip'        => $data['ip'],
            'remark'    => $data['remark'] ?? null,
            'expire_at' => $data['expire_at'] ?? null,
        ]);

        // Invalidate middleware cache so the new ban takes effect at once/
        // 失效中间件缓存，新封禁立即生效
        Cache::forget(self::CACHE_KEY);

        return $model;
    }

    /**
     * Physically delete a blacklist row by snow_id/按 snow_id 物理删除封禁记录
     */
    public function destroyBySnowId(int $snowId): void
    {
        $model = IpBlacklist::query()->where('snow_id', $snowId)->first();

        if ($model === null) {
            throw ApiException::notFound('api.ip.not_found');
        }

        $model->delete();

        // Invalidate middleware cache so the unblock takes effect at once/
        // 失效中间件缓存，解封立即生效
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Normalize paginator output to {list,total,page,page_size}/统一分页输出为 {list,total,page,page_size}
     *
     * @return array{list: array<int, IpBlacklist>, total: int, page: int, page_size: int}
     */
    private function formatPage(LengthAwarePaginator $paginator, int $page, int $pageSize): array
    {
        return [
            'list'      => $paginator->items(),
            'total'     => $paginator->total(),
            'page'      => $page,
            'page_size' => $pageSize,
        ];
    }
}
