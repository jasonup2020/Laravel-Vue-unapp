<?php

declare(strict_types=1);

namespace App\Base;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Base repository/Repository 基类
 *
 * Centralizes CRUD, pagination (page/page_size ≤ 100), keyset pagination,
 * cache hooks and bulk insert. Subclasses bind a concrete Model class.
 * 集中提供 CRUD、分页（page/page_size ≤ 100）、keyset 深分页、缓存钩子与批量写入；
 * 子类通过构造函数绑定具体 Model 类名。
 */
abstract class BaseRepository
{
    /**
     * Maximum page size/分页大小上限
     */
    protected const int MAX_PAGE_SIZE = 100;

    /**
     * Default cursor column for keyset pagination/keyset 分页默认游标列
     */
    protected const string DEFAULT_CURSOR_COLUMN = 'snow_id';

    /**
     * The Eloquent model class name/绑定的 Eloquent 模型类名
     *
     * @var class-string<Model>
     */
    protected string $modelClass;

    /**
     * Constructor/构造函数
     *
     * @param  class-string<Model>  $modelClass  e.g. \App\Models\Product::class
     */
    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
    }

    /**
     * New query builder/获取新查询构造器
     */
    public function query(): Builder
    {
        return $this->modelClass::query();
    }

    /**
     * Find by primary id/按主键 id 查询
     */
    public function find(int|string $id): ?Model
    {
        return $this->query()->find($id);
    }

    /**
     * Find by primary id or throw 404/按主键 id 查询，未找到抛 404
     *
     * @throws ApiException
     */
    public function findOrFail(int|string $id): Model
    {
        $model = $this->find($id);
        if ($model === null) {
            throw ApiException::notFound('api.not_found');
        }

        return $model;
    }

    /**
     * Find by snow_id (public-facing id)/按对外 snow_id 查询
     */
    public function findBySnowId(int|string $snowId): ?Model
    {
        return $this->query()->where(self::DEFAULT_CURSOR_COLUMN, $snowId)->first();
    }

    /**
     * Find by snow_id or throw 404/按 snow_id 查询，未找到抛 404
     *
     * @throws ApiException
     */
    public function findBySnowIdOrFail(int|string $snowId): Model
    {
        $model = $this->findBySnowId($snowId);
        if ($model === null) {
            throw ApiException::notFound('api.not_found');
        }

        return $model;
    }

    /**
     * Paginated list/分页列表
     *
     * Returns shape {list, total, page, page_size}; page_size clamped to 100.
     * 返回结构 {list, total, page, page_size}；page_size 钳制到 100。
     *
     * @param  array<string,mixed>  $filters  where conditions/where 条件
     * @return array{list:Collection<int,Model>,total:int,page:int,page_size:int}
     */
    public function paginate(int $page, int $pageSize, array $filters = [], array $with = []): array
    {
        $page = max(1, $page);
        $pageSize = $this->clampPageSize($pageSize);

        $paginator = $this->newFilteredQuery($filters)
            ->with($with)
            ->orderByDesc('id')
            ->paginate($pageSize, ['*'], 'page', $page);

        return [
            'list'     => $paginator->getCollection(),
            'total'    => $paginator->total(),
            'page'     => $page,
            'page_size'=> $pageSize,
        ];
    }

    /**
     * Keyset / cursor pagination for deep paging/keyset 游标分页（深分页）
     *
     * For large tables avoid OFFSET; use the last seen cursor value and iterate
     * `WHERE cursor_column < cursor ORDER BY cursor_column DESC LIMIT n`.
     * 大表避免 OFFSET 深分页：传入上次最后一条的游标值，按
     * `WHERE cursor_column < cursor ORDER BY cursor_column DESC LIMIT n` 翻页。
     *
     * @param  string  $cursorColumn  defaults to snow_id/默认 snow_id
     * @return Collection<int,Model>
     */
    public function keysetPaginate(int $cursor, int $pageSize, string $cursorColumn = self::DEFAULT_CURSOR_COLUMN, array $filters = [], array $with = []): Collection
    {
        $pageSize = $this->clampPageSize($pageSize);

        return $this->newFilteredQuery($filters)
            ->with($with)
            ->where($cursorColumn, '<', $cursor)
            ->orderByDesc($cursorColumn)
            ->limit($pageSize)
            ->get();
    }

    /**
     * Create a record/新建记录
     *
     * @param  array<string,mixed>  $data
     */
    public function create(array $data): Model
    {
        $modelClass = $this->modelClass;

        return $modelClass::create($data);
    }

    /**
     * Bulk insert many rows (no model events)/批量插入（不触发模型事件）
     *
     * Use for high-volume imports; Eloquent timestamps must be filled manually.
     * 高吞吐导入场景使用；Eloquent timestamps 需自行写入。
     *
     * @param  array<int,array<string,mixed>>  $rows
     */
    public function insertMany(array $rows): bool
    {
        if ($rows === []) {
            return true;
        }
        $now = now();
        $rows = array_map(static function (array $row) use ($now) {
            $row['created_at'] ??= $now;
            $row['updated_at'] ??= $now;

            return $row;
        }, $rows);

        return $this->query()->insert($rows);
    }

    /**
     * Update a model instance/更新模型实例
     *
     * @param  array<string,mixed>  $data
     */
    public function update(Model $model, array $data): Model
    {
        $model->fill($data)->save();

        return $model->refresh();
    }

    /**
     * Soft-delete a model instance/软删除模型实例
     *
     * Falls back to forceDelete when the model has no SoftDeletes trait.
     * 模型未启用 SoftDeletes 时退化为物理删除。
     */
    public function delete(Model $model): bool
    {
        if (method_exists($model, 'trashed')) {
            return (bool) $model->delete();
        }

        return (bool) $model->forceDelete();
    }

    /**
     * Restore a soft-deleted model/恢复软删除模型
     */
    public function restore(Model $model): Model
    {
        if (method_exists($model, 'restore')) {
            $model->restore();
        }

        return $model->refresh();
    }

    /**
     * Force delete (physical)/物理删除
     */
    public function forceDelete(Model $model): bool
    {
        return (bool) $model->forceDelete();
    }

    // ---------------------------------------------------------------------
    // Cache hooks/缓存钩子
    // ---------------------------------------------------------------------

    /**
     * Cache key for this repository/当前 Repository 的缓存 key
     *
     * Return null to disable caching; subclasses override to enable cache-aside.
     * 返回 null 表示不启用缓存；子类覆写以开启 cache-aside。
     */
    protected function cacheKey(): ?string
    {
        return null;
    }

    /**
     * Forget the cached value/清理缓存
     */
    public function forgetCache(): void
    {
        $key = $this->cacheKey();
        if ($key !== null) {
            Cache::forget($key);
        }
    }

    /**
     * Remember a value via Cache::remember/读缓存或回源
     *
     * @template T
     * @param  callable():T  $callback
     * @return T|null
     */
    protected function remember(int $ttl, callable $callback): mixed
    {
        $key = $this->cacheKey();
        if ($key === null) {
            return $callback();
        }

        return Cache::remember($key, $ttl, $callback);
    }

    // ---------------------------------------------------------------------
    // Internal helpers/内部辅助
    // ---------------------------------------------------------------------

    /**
     * Clamp page size to [1,100]/将 page_size 钳制到 [1,100]
     */
    protected function clampPageSize(int $pageSize): int
    {
        if ($pageSize < 1) {
            $pageSize = self::MAX_PAGE_SIZE > 15 ? 15 : 15;
        }
        if ($pageSize > self::MAX_PAGE_SIZE) {
            $pageSize = self::MAX_PAGE_SIZE;
        }

        return $pageSize;
    }

    /**
     * Build a query with equality filters/按等值条件构造查询
     *
     * @param  array<string,mixed>  $filters
     */
    protected function newFilteredQuery(array $filters): Builder
    {
        $query = $this->query();
        foreach ($filters as $col => $val) {
            if ($val === null || $val === '') {
                continue;
            }
            if (is_array($val)) {
                $query->whereIn($col, $val);
            } else {
                $query->where($col, $val);
            }
        }

        return $query;
    }
}
