<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\AdminFavorite;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Admin favorite service/管理端收藏查询服务
 *
 * Full-isolation extension: admin-side read-only list only. This layer MUST NOT reference
 * any App\Models\UserFavorite / App\Services\User\* / App\Http\Controllers\Api\User\* class.
 * Uses App\Models\AdminFavorite (shares the same physical table user_favorites).
 * 全隔离扩展示例：管理端只读列表。本层禁止引用任何 C 端 User* 业务类，
 * 使用 AdminFavorite 模型（与 UserFavorite 同表）。
 */
class FavoriteService
{
    /**
     * Paginate all favorites with user + product preloaded/分页查询全部收藏（预加载用户+商品）
     *
     * Optional filter by user outward snow_id; sorted by created_at desc.
     * 可选按用户对外 snow_id 过滤；按 created_at 倒序。
     *
     * @param  int|null $userSnowId Optional user outward snow_id filter/可选用户对外 snow_id 过滤
     * @param  int      $page       Page number/页码
     * @param  int      $pageSize    Page size (clamped to <=100)/每页条数（上限 100）
     * @return array{list: Collection<int, AdminFavorite>, total: int, page: int, page_size: int}
     */
    public function index(?int $userSnowId, int $page, int $pageSize): array
    {
        // Clamp page size to the hard cap (<=100)/page_size 钳制到上限 100
        $pageSize = max(1, min($pageSize, 100));

        /** @var LengthAwarePaginator<AdminFavorite> $paginator */
        $paginator = AdminFavorite::query()
            // Eager-load user (public profile columns) and product (public columns); avoids N+1/
            // 预加载用户公开资料与商品公开列，避免 N+1
            ->with([
                'user:id,snow_id,name',
                'product:id,snow_id,name,price,status',
            ])
            // Optional filter by user outward snow_id (resolved via relation, no internal id leak)/
            // 可选按用户对外 snow_id 过滤（走关联解析，不暴露内部 id）
            ->when($userSnowId !== null, fn (Builder $q) => $q->whereHas(
                'user',
                fn (Builder $w) => $w->where('snow_id', $userSnowId)
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id') // stable tie-breaker for pagination/稳定分页兜底
            ->paginate($pageSize, ['*'], 'page', $page);

        return [
            // $hidden already hides auto-increment id per row/$hidden 已逐行隐藏自增 id
            'list'      => $paginator->getCollection(),
            'total'     => $paginator->total(),
            'page'      => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ];
    }
}
