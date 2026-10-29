<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Enums\ProductStatus;
use App\Exceptions\ApiException;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * C-end product service/C 端商品服务
 *
 * Read-only on-shelf products; list + detail with cache-aside on detail
 * C 端只读上架商品；列表分页，详情走缓存旁路
 */
class ProductService
{
    /** Detail cache TTL in seconds/详情缓存秒数 */
    public const SHOW_CACHE_TTL = 300;

    /**
     * List on-shelf products by page/分页查询上架商品列表
     *
     * @param string|null $name  Optional fuzzy name filter/可选名称模糊搜索
     * @param int         $page  Current page/当前页码
     * @param int         $pageSize Page size clamped to <=100/每页条数（上限100）
     * @return array{list: Collection<int, Product>, total: int, page: int, page_size: int}
     */
    public function list(?string $name, int $page, int $pageSize): array
    {
        $query = Product::query()
            ->where('status', ProductStatus::ON->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id'); // tie-breaker for stable pagination/稳定分页兜底排序

        // Optional fuzzy name search/可选名称模糊搜索
        if (!empty($name)) {
            $query->where('name', 'like', '%' . $name . '%');
        }

        /** @var LengthAwarePaginator<Product> $paginator */
        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

        return [
            // $hidden already hides auto-increment id per item/$hidden 已逐行隐藏自增 id
            'list'      => $paginator->getCollection(),
            'total'     => $paginator->total(),
            'page'      => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ];
    }

    /**
     * Get on-shelf product detail by snow_id (cache-aside)/
     * 按 snow_id 获取上架商品详情（缓存旁路）
     *
     * @param int $snowId Snowflake business ID/雪花业务ID
     * @return array<string, mixed>
     */
    public function showBySnowId(int $snowId): array
    {
        $key = "product:show:{$snowId}";

        // Cache-aside hit/缓存命中直接返回
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        // Only on-shelf products are visible to C-end/C 端仅可见上架商品
        $product = Product::query()
            ->where('snow_id', $snowId)
            ->where('status', ProductStatus::ON->value)
            ->first();

        if (!$product) {
            // Not found or off-shelf/不存在或已下架
            throw ApiException::notFound('api.product.not_found');
        }

        // $hidden already hides auto-increment id/$hidden 已隐藏自增 id
        $data = $product->toArray();
        Cache::put($key, $data, self::SHOW_CACHE_TTL);

        return $data;
    }
}
