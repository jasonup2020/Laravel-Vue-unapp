<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\Product;
use App\Models\UserFavorite;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * C-end favorite service/C 端用户收藏服务
 *
 * Full-isolation extension: C-end CRUD only. This layer MUST NOT reference any
 * App\Models\AdminFavorite / App\Services\Admin\* / App\Http\Controllers\Api\Admin\* class.
 * Only the physical table (user_favorites) and the shared migration are reused.
 * 全隔离扩展示例：仅 C 端 CRUD。本层禁止引用任何 Admin* 类，仅共享物理表与迁移。
 *
 * Soft-delete vs unique-index decision/软删与唯一索引的冲突处理：
 * user_favorites carries a UNIQUE index on (user_id, product_id). If "unfavorite" used
 * SoftDeletes (delete()), the row would remain with deleted_at set, and re-favoriting
 * the same product would violate the unique index. Therefore canceling a favorite MUST
 * forceDelete() (physical row removal) so re-favorite works. Trashed rows (if any, e.g.
 * created by other flows) are bypassed via withTrashed() lookup before force delete.
 * user_favorites 表有 (user_id, product_id) 唯一索引。若取消收藏走软删（delete()），
 * 记录仍在表内（仅置 deleted_at），再次收藏会撞唯一索引。故取消收藏必须 forceDelete 物理删除，
 * 以保证"取消后可再次收藏"。若存在历史软删记录，先 withTrashed() 定位再 forceDelete。
 */
class FavoriteService
{
    /**
     * Paginate my favorites with product preloaded/分页查询我的收藏（预加载商品）
     *
     * Sorted by created_at desc; product relation selects public columns only
     * (snow_id/name/price/status) to avoid leaking internal fields.
     * 按 created_at 倒序；product 关联仅取公开列（snow_id/name/price/status）。
     *
     * @param  int $userId   Current api-guard user id (auto-increment)/当前 api 守卫用户自增 id
     * @param  int $page     Page number/页码
     * @param  int $pageSize Page size (clamped to <=100)/每页条数（上限 100）
     * @return array{list: Collection<int, UserFavorite>, total: int, page: int, page_size: int}
     */
    public function list(int $userId, int $page, int $pageSize): array
    {
        // Clamp page size to the hard cap (<=100)/page_size 钳制到上限 100
        $pageSize = max(1, min($pageSize, 100));

        /** @var LengthAwarePaginator<UserFavorite> $paginator */
        $paginator = UserFavorite::query()
            ->where('user_id', $userId)
            // Eager-load product public columns only; avoids N+1 and internal id leak/
            // 预加载商品公开列，避免 N+1 并屏蔽内部 id
            ->with(['product:id,snow_id,name,price,status'])
            ->orderByDesc('created_at')
            ->orderByDesc('id') // stable tie-breaker for pagination/稳定分页兜底
            ->paginate($pageSize, ['*'], 'page', $page);

        return [
            // $hidden already hides auto-increment id per favorite row/$hidden 已逐行隐藏自增 id
            'list'      => $paginator->getCollection(),
            'total'     => $paginator->total(),
            'page'      => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ];
    }

    /**
     * Favorite a product by its outward snow_id/按商品 snow_id 收藏
     *
     * Duplicate (user_id, product_id) with a non-trashed row → 409 api.favorite.duplicate.
     * 重复收藏（同一用户同一商品且存在未删除记录）→ 409 api.favorite.duplicate。
     *
     * @param  int $userId       Current api-guard user id/当前 api 守卫用户自增 id
     * @param  int $productSnowId Product outward snow_id/商品对外 snow_id
     */
    public function add(int $userId, int $productSnowId): UserFavorite
    {
        return DB::transaction(function () use ($userId, $productSnowId): UserFavorite {
            // Resolve product by outward snow_id; favoritable object must exist/
            // 按对外 snow_id 解析商品，收藏对象须存在
            $product = Product::query()->where('snow_id', $productSnowId)->first();
            if (!$product) {
                // Already covered by Rule::exists in FormRequest, but defense-in-depth/
                // FormRequest 已 exists 校验，此处为纵深防御
                throw ApiException::notFound('api.product.not_found');
            }

            // Duplicate check: only non-trashed rows count (default scope excludes trashed)/
            // 重复判断：仅统计未软删记录（默认作用域已排除 trashed）
            $duplicated = UserFavorite::query()
                ->where('user_id', $userId)
                ->where('product_id', $product->id)
                ->exists();

            if ($duplicated) {
                throw new ApiException(ErrorCode::CONFLICT, 'api.favorite.duplicate');
            }

            return UserFavorite::query()->create([
                'user_id'    => $userId,
                'product_id' => $product->id,
            ]);
        });
    }

    /**
     * Check whether the current user has favorited a product by its snow_id/
     * 查询当前用户是否已收藏某商品（按商品 snow_id）
     *
     * @return array{favorited: bool}
     */
    public function status(int $userId, int $productSnowId): array
    {
        $favorited = UserFavorite::query()
            ->where('user_id', $userId)
            ->whereIn('product_id', function ($query) use ($productSnowId): void {
                // Resolve product internal id from outward snow_id via sub-select/
                // 通过子查询由对外 snow_id 解析商品自增 id
                $query->select('id')
                    ->from('products')
                    ->where('snow_id', $productSnowId);
            })
            ->exists();

        return ['favorited' => $favorited];
    }

    /**
     * Cancel a favorite by its outward snow_id (owner only)/
     * 按收藏记录 snow_id 取消收藏（仅本人可删）
     *
     * Force-deletes the row to avoid the (user_id, product_id) unique-index conflict on
     * re-favorite; throws 404 when the favorite does not exist or is not owned by me.
     * 物理 forceDelete 以避免再次收藏撞唯一索引；记录不存在或非本人抛 404。
     *
     * @param  int $userId Current api-guard user id/当前 api 守卫用户自增 id
     * @param  int $snowId Favorite outward snow_id/收藏记录对外 snow_id
     */
    public function remove(int $userId, int $snowId): void
    {
        // Owner-scoped lookup by outward snow_id (includes any historically trashed rows)/
        // 按对外 snow_id 在本人范围内查找（含历史软删记录，便于 forceDelete）
        $favorite = UserFavorite::query()
            ->withTrashed()
            ->where('snow_id', $snowId)
            ->where('user_id', $userId)
            ->first();

        if (!$favorite) {
            // Not found or not mine/记录不存在或非本人
            throw ApiException::notFound('api.favorite.not_found');
        }

        DB::transaction(function () use ($favorite): void {
            // Physical delete: keeps (user_id, product_id) unique index reusable on re-favorite/
            // 物理删除：保证 (user_id, product_id) 唯一索引可被再次收藏复用
            $favorite->forceDelete();
        });
    }
}
