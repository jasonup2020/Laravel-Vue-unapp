<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\CMenu;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * Admin C-end menu service/管理端C端菜单服务
 *
 * Full CRUD for C-end public menus; writes clear c_menu:list cache
 * C 端公开菜单完整 CRUD；写操作清理 c_menu:list 缓存
 */
class CMenuService
{
    /** Cache key for C-end public menu list/C端公开菜单列表缓存键 */
    private const CACHE_KEY = 'c_menu:list';

    /**
     * Paginate C-end menus (including hidden)/分页查询 C 端菜单（含隐藏项）
     *
     * @return array{list: array<int, array<string, mixed>>, total: int, page: int, page_size: int}
     */
    public function index(int $page, int $pageSize): array
    {
        $pageSize = min(max(1, $pageSize), 100);

        /** @var LengthAwarePaginator<CMenu> $paginator */
        $paginator = CMenu::query()->orderByDesc('id')->paginate($pageSize, ['*'], 'page', $page);

        $list = collect($paginator->items())
            ->map(fn (CMenu $m) => $this->format($m))
            ->all();

        return [
            'list' => $list,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ];
    }

    /**
     * Create a C-end menu/创建 C 端菜单
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function store(array $data): array
    {
        $menu = CMenu::query()->create($this->normalize($data));
        $this->clearCache();

        return $this->format($menu);
    }

    /**
     * Update a C-end menu by snow_id/按 snow_id 更新 C 端菜单
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $snowId, array $data): array
    {
        $menu = $this->findBySnowId($snowId);

        // Cycle prevention: validate parent_id before writing/
        // 防环校验：写入前校验 parent_id
        if (array_key_exists('parent_id', $data)) {
            $newParentId = (int) $data['parent_id'];
            $this->assertNoCycle($snowId, $newParentId);
            $menu->parent_id = $newParentId;
        }
        if (array_key_exists('title', $data)) {
            $menu->title = $data['title'];
        }
        if (array_key_exists('title_en', $data)) {
            $menu->title_en = $data['title_en'];
        }
        if (array_key_exists('icon', $data)) {
            $menu->icon = $data['icon'];
        }
        if (array_key_exists('path', $data)) {
            $menu->path = $data['path'];
        }
        if (array_key_exists('url', $data)) {
            $menu->url = $data['url'];
        }
        if (array_key_exists('sort', $data)) {
            $menu->sort = (int) $data['sort'];
        }
        // Boolean must use array_key_exists (not array_filter) so false/0 writes through/
        // 布尔字段用 array_key_exists 判断，确保 false/0 也能写入
        if (array_key_exists('is_show', $data)) {
            $menu->is_show = (bool) $data['is_show'];
        }

        $menu->save();
        $this->clearCache();

        return $this->format($menu->refresh());
    }

    /**
     * Assert that setting parent_id to $newParentId would not create a cycle/
     * 断言将 parent_id 设为 $newParentId 不会形成环
     *
     * parent_id column stores snow_id values (not auto-increment id)/
     * parent_id 列存储的是 snow_id 值（不是自增 id）
     *
     * @throws ApiException when cycle detected/检测到环时抛出
     */
    private function assertNoCycle(int $selfSnowId, int $newParentId): void
    {
        // Root is always safe/根节点总是安全的
        if ($newParentId === 0) {
            return;
        }

        // Cannot set self as parent/不能将自身设为父级
        if ($newParentId === $selfSnowId) {
            \Log::info('[CMenuCycle] SELF check rejected', ['self' => $selfSnowId, 'newParent' => $newParentId]);
            throw new ApiException(ErrorCode::UNPROCESSABLE, 'api.menu.parent_cannot_self');
        }

        // Collect all descendant snow_ids via BFS from self/
        // 从 self 出发 BFS 收集所有子孙 snow_id
        $descendantSnowIds = $this->collectDescendantSnowIds($selfSnowId);

        \Log::info('[CMenuCycle] BFS result', [
            'self' => $selfSnowId,
            'newParent' => $newParentId,
            'descendants_of_self' => $descendantSnowIds,
            'newParent_in_descendants' => in_array($newParentId, $descendantSnowIds, true),
        ]);

        if (in_array($newParentId, $descendantSnowIds, true)) {
            throw new ApiException(ErrorCode::UNPROCESSABLE, 'api.menu.parent_cannot_descendant');
        }
    }

    /**
     * Collect all descendant snow_ids via BFS/通过 BFS 收集所有子孙 snow_id
     *
     * parent_id stores snow_id, so we match children by parent_id = snow_id/
     * parent_id 存储 snow_id，故用 parent_id = snow_id 匹配子节点
     *
     * @return list<int>
     */
    private function collectDescendantSnowIds(int $startSnowId): array
    {
        $descendants = [];
        // BFS queue holds snow_ids to explore/
        // BFS 队列存放待遍历的 snow_id
        $queue = [$startSnowId];

        while (! empty($queue)) {
            $currentSnowId = array_shift($queue);

            // Find children where parent_id = current snow_id/
            // 查找 parent_id 等于当前 snow_id 的子节点
            $children = CMenu::query()
                ->where('parent_id', $currentSnowId)
                ->pluck('snow_id')
                ->all();

            foreach ($children as $childSnowId) {
                $childSnowId = (int) $childSnowId;
                $descendants[] = $childSnowId;
                $queue[] = $childSnowId;
            }
        }

        return $descendants;
    }

    /**
     * Soft delete a C-end menu/软删除 C 端菜单
     */
    public function destroy(int $snowId): void
    {
        $menu = $this->findBySnowId($snowId);
        $menu->delete(); // SoftDeletes/软删除
        $this->clearCache();
    }

    /**
     * Find CMenu by snow_id or throw/按 snow_id 查找
     */
    private function findBySnowId(int $snowId): CMenu
    {
        $menu = CMenu::query()->where('snow_id', $snowId)->first();
        if (! $menu) {
            throw ApiException::notFound('api.not_found');
        }

        return $menu;
    }

    /**
     * Clear C-end public menu cache/清理 C 端公开菜单缓存
     */
    private function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Normalize input data with defaults/规范化入参并填充默认值
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        return [
            'parent_id' => (int) ($data['parent_id'] ?? 0),
            'title' => $data['title'] ?? null,
            'title_en' => $data['title_en'] ?? null,
            'icon' => $data['icon'] ?? null,
            'path' => $data['path'] ?? null,
            'url' => $data['url'] ?? null,
            'sort' => (int) ($data['sort'] ?? 0),
            'is_show' => (bool) ($data['is_show'] ?? true),
        ];
    }

    /**
     * Format CMenu model into array/格式化 CMenu 模型为数组
     *
     * @return array<string, mixed>
     */
    private function format(CMenu $menu): array
    {
        return $menu->toArray();
    }
}
