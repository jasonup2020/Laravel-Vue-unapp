<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Exceptions\ApiException;
use App\Models\Dict;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin dict service/管理端数据字典服务
 *
 * Full CRUD; dicts has no soft-delete column so destroy is physical delete;
 * every write clears the corresponding type cache
 * 完整增删改查；dicts 表无软删列，destroy 为物理删除；写操作清理对应 type 缓存
 */
class DictService
{
    /**
     * Paginate dict items with optional type filter/分页查询字典项（可按 type 筛选）
     *
     * @param string|null $type Optional dict type filter/可选字典类型筛选
     * @param int         $page Current page/当前页码
     * @param int         $pageSize Page size (<=100)/每页条数（上限100）
     * @return array{list: Collection<int, Dict>, total: int, page: int, page_size: int}
     */
    public function index(?string $type, int $page, int $pageSize): array
    {
        $query = Dict::query()->orderByDesc('id');

        if (!empty($type)) {
            $query->where('type', $type);
        }

        /** @var LengthAwarePaginator<Dict> $paginator */
        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

        // Transform each item to bilingual label format/逐项转换为双语 label 格式
        $list = $paginator->getCollection()->map(fn (Dict $d) => $this->formatDict($d))->all();

        return [
            'list'      => $list,
            'total'     => $paginator->total(),
            'page'      => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ];
    }

    /**
     * Create a dict item/创建字典项
     *
     * @param array<string, mixed> $data Validated input/校验后入参
     * @return Dict
     */
    public function store(array $data): array
    {
        $dict = DB::transaction(fn (): Dict => Dict::query()->create($data));

        // Clear cache of the affected type/清理受影响 type 的缓存
        $this->forgetTypeCache((string) $dict->type);

        return $this->formatDict($dict);
    }

    /**
     * Update a dict item by snow_id/按 snow_id 更新字典项
     *
     * @param int                  $snowId Snowflake ID/雪花业务ID
     * @param array<string, mixed> $data  Validated input/校验后入参
     * @return Dict
     */
    public function update(int $snowId, array $data): array
    {
        $dict = Dict::query()->where('snow_id', $snowId)->first();
        if (!$dict) {
            throw ApiException::notFound('api.dict.not_found');
        }

        $oldType = (string) $dict->type;

        DB::transaction(function () use ($dict, $data): void {
            $dict->fill($data)->save();
        });

        // Clear both old and new type cache if type changed/
        // 若 type 变更，同时清理新旧两个 type 缓存
        $this->forgetTypeCache($oldType);
        $this->forgetTypeCache((string) $dict->type);

        return $this->formatDict($dict->refresh());
    }

    /**
     * Physically delete a dict item by snow_id/按 snow_id 物理删除字典项
     *
     * @param int $snowId Snowflake ID/雪花业务ID
     */
    public function destroy(int $snowId): void
    {
        $dict = Dict::query()->where('snow_id', $snowId)->first();
        if (!$dict) {
            throw ApiException::notFound('api.dict.not_found');
        }

        $type = (string) $dict->type;

        DB::transaction(function () use ($dict): void {
            $dict->forceDelete(); // dicts has no soft-delete column/无软删列，物理删除
        });

        $this->forgetTypeCache($type);
    }

    /**
     * Format a dict model into array with bilingual label/
     * 格式化字典模型为含双语 label 的数组
     *
     * @return array<string, mixed>
     */
    private function formatDict(Dict $dict): array
    {
        $arr = $dict->toArray();
        // Bilingual label object/双语 label 对象
        $arr['label'] = [
            'zh-CN' => $dict->label,
            'en' => $dict->label_en ?? $dict->label,
        ];
        unset($arr['label_en']);

        return $arr;
    }

    /**
     * Clear cached list for one dict type/清理指定 type 的字典列表缓存
     *
     * @param string $type Dict type/字典类型
     */
    private function forgetTypeCache(string $type): void
    {
        Cache::forget("dict:type:{$type}");
    }
}
