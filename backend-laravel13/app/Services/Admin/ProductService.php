<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Exceptions\ApiException;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin product service/管理端商品服务
 *
 * Full CRUD; soft-delete; clear C-end detail cache on writes
 * 完整增删改查；软删除；写操作清理 C 端详情缓存
 */
class ProductService
{
    /**
     * Paginate products with optional filters/分页查询商品（可按状态/名称筛选）
     *
     * @param int|null    $status   Optional status filter/可选状态筛选
     * @param string|null $name     Optional fuzzy name filter/可选名称模糊搜索
     * @param int         $page     Current page/当前页码
     * @param int         $pageSize Page size (<=100)/每页条数（上限100）
     * @return array{list: Collection<int, Product>, total: int, page: int, page_size: int}
     */
    public function index(?int $status, ?string $name, int $page, int $pageSize): array
    {
        $query = Product::query()->orderByDesc('id');

        if ($status !== null) {
            $query->where('status', $status);
        }

        if (!empty($name)) {
            $query->where('name', 'like', '%' . $name . '%');
        }

        /** @var LengthAwarePaginator<Product> $paginator */
        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

        return [
            'list'      => $paginator->getCollection(),
            'total'     => $paginator->total(),
            'page'      => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ];
    }

    /**
     * Create a product/创建商品
     *
     * @param array<string, mixed> $data Validated input/校验后入参
     * @return Product
     */
    public function store(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            return Product::query()->create($data);
        });
    }

    /**
     * Update a product by snow_id/按 snow_id 更新商品
     *
     * @param int                  $snowId Snowflake ID/雪花业务ID
     * @param array<string, mixed> $data   Validated input/校验后入参
     * @return Product
     */
    public function update(int $snowId, array $data): Product
    {
        $product = Product::query()->where('snow_id', $snowId)->first();
        if (!$product) {
            throw ApiException::notFound('api.product.not_found');
        }

        DB::transaction(function () use ($product, $data): void {
            $product->fill($data)->save();
        });

        // Invalidate C-end detail cache after update/更新后清理 C 端详情缓存
        $this->forgetShowCache($snowId);

        return $product->refresh();
    }

    /**
     * Soft-delete a product by snow_id/按 snow_id 软删除商品
     *
     * @param int $snowId Snowflake ID/雪花业务ID
     */
    public function destroy(int $snowId): void
    {
        $product = Product::query()->where('snow_id', $snowId)->first();
        if (!$product) {
            throw ApiException::notFound('api.product.not_found');
        }

        DB::transaction(function () use ($product): void {
            $product->delete(); // SoftDeletes/软删除
        });

        // Invalidate C-end detail cache after delete/删除后清理 C 端详情缓存
        $this->forgetShowCache($snowId);
    }

    /**
     * Forget C-end detail cache for one product/清理指定商品的 C 端详情缓存
     *
     * Note: list caching is intentionally skipped (no wildcard flush available);
     * only the detail key product:show:{snow_id} is maintained/
     * 注意：刻意不做列表缓存（Cache::forget 不支持通配），仅维护详情键
     *
     * @param int $snowId Snowflake ID/雪花业务ID
     */
    private function forgetShowCache(int $snowId): void
    {
        Cache::forget("product:show:{$snowId}");
    }
}
