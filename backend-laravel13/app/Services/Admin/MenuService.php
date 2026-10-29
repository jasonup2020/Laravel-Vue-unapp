<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\AdminStatus;
use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Models\AdminMenu;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin menu service/后台菜单服务
 *
 * Builds menu trees with Redis caching (key: admin_menu:tree:{adminId}, TTL 7 days)
 * 构建菜单树并使用 Redis 缓存（key: admin_menu:tree:{adminId}，TTL 7 天）
 */
class MenuService
{
    /** Cache TTL in seconds (7 days)/缓存 TTL 秒数（7 天） */
    private const CACHE_TTL = 604800;

    /**
     * Get current admin's menu tree (cached)/获取当前管理员菜单树（走缓存）
     *
     * Super-admin sees the full tree; others see menus bound to their roles
     * 超管看全树，其他管理员看角色绑定的菜单
     *
     * @return array<int, array<string, mixed>>
     */
    public function myTree(int $adminId): array
    {
        return Cache::remember(
            "admin_menu:tree:{$adminId}",
            self::CACHE_TTL,
            fn () => $this->buildMyTree($adminId)
        );
    }

    /**
     * Build the menu tree for an admin (not cached)/为指定管理员构建菜单树（不缓存）
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildMyTree(int $adminId): array
    {
        // Check if this admin has super-admin role/判断是否超管
        $isSuper = Admin::query()
            ->where('id', $adminId)
            ->whereHas('roles', function ($q) {
                $q->where('code', 'super-admin');
            })
            ->exists();

        if ($isSuper) {
            // Super-admin: all menus/超管：全部菜单
            $menus = AdminMenu::query()->orderBy('sort')->orderBy('id')->get();
        } else {
            // Regular admin: menus reachable via their roles/普通管理员：通过角色可达的菜单
            $menus = $this->menusForAdmin($adminId);
        }

        return $this->buildTree($menus);
    }

    /**
     * Get the full menu tree directly from DB (no cache)/从数据库直读全菜单树（不走缓存）
     *
     * @return array<int, array<string, mixed>>
     */
    public function allTree(): array
    {
        $menus = AdminMenu::query()->orderBy('sort')->orderBy('id')->get();

        return $this->buildTree($menus);
    }

    /**
     * Create a menu node then clear all admins' menu cache/创建菜单节点并清理全部管理员菜单缓存
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): AdminMenu
    {
        $menu = AdminMenu::query()->create($this->normalizeData($data));
        $this->clearAllMenuCache();

        return $menu;
    }

    /**
     * Update a menu node then clear all admins' menu cache/更新菜单节点并清理全部管理员菜单缓存
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int $snowId, array $data): AdminMenu
    {
        $menu = $this->findBySnowId($snowId);
        $menu->fill($this->normalizeData($data));
        $menu->save();

        $this->clearAllMenuCache();

        return $menu;
    }

    /**
     * Soft delete a menu node then clear all admins' menu cache/软删菜单节点并清理全部管理员菜单缓存
     */
    public function destroy(int $snowId): void
    {
        $menu = $this->findBySnowId($snowId);
        $menu->delete(); // SoftDeletes/软删除
        $this->clearAllMenuCache();
    }

    /**
     * Clear menu cache for every active admin/遍历全部启用管理员，逐个清理菜单缓存
     */
    public function clearAllMenuCache(): void
    {
        $adminIds = Admin::query()
            ->where('status', AdminStatus::ACTIVE->value)
            ->pluck('id')
            ->all();

        foreach ($adminIds as $adminId) {
            Cache::forget("admin_menu:tree:{$adminId}");
        }
    }

    /**
     * Find menu by snow_id or throw/按 snow_id 查找菜单
     */
    private function findBySnowId(int $snowId): AdminMenu
    {
        $menu = AdminMenu::query()->where('snow_id', $snowId)->first();
        if (! $menu) {
            throw ApiException::notFound('api.menu.not_found');
        }

        return $menu;
    }

    /**
     * Query menus accessible by a given admin via their roles/查询指定管理员通过角色可访问的菜单
     *
     * @return Collection<int, AdminMenu>
     */
    private function menusForAdmin(int $adminId): Collection
    {
        return AdminMenu::query()
            ->whereExists(function ($query) use ($adminId): void {
                $query->select(DB::raw(1))
                    ->from('role_menu')
                    ->join('admin_role', 'admin_role.role_id', '=', 'role_menu.role_id')
                    ->whereColumn('role_menu.menu_id', 'admin_menus.id')
                    ->where('admin_role.admin_id', $adminId);
            })
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    /**
     * Build nested tree from flat menu collection/从平铺菜单集合递归组装树形结构
     *
     * @param  Collection<int, AdminMenu>  $menus
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(Collection $menus, int $parentId = 0): array
    {
        $tree = [];

        foreach ($menus as $menu) {
            if ((int) $menu->parent_id === $parentId) {
                $item = $menu->toArray();
                // Bilingual title object: {zh-CN: title, en: title_en ?? title}/
                // 双语标题对象：{zh-CN: 中文标题, en: 英文标题（缺省回退中文）}
                $item['title'] = [
                    'zh-CN' => $menu->title,
                    'en' => $menu->title_en ?? $menu->title,
                ];
                // Remove raw title_en from output to keep payload clean/移除原始 title_en 字段保持输出简洁
                unset($item['title_en']);
                $item['children'] = $this->buildTree($menus, (int) $menu->snow_id);
                $tree[] = $item;
            }
        }

        return $tree;
    }

    /**
     * Normalize input data with defaults/规范化入参并填充默认值
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeData(array $data): array
    {
        return [
            'parent_id' => (int) ($data['parent_id'] ?? 0),
            'title' => $data['title'] ?? null,
            'title_en' => $data['title_en'] ?? null,
            'path' => $data['path'] ?? null,
            'component' => $data['component'] ?? null,
            'icon' => $data['icon'] ?? null,
            'permission_code' => $data['permission_code'] ?? null,
            'sort' => (int) ($data['sort'] ?? 0),
            'is_show' => (bool) ($data['is_show'] ?? true),
            'is_cache' => (bool) ($data['is_cache'] ?? true),
            'is_external' => (bool) ($data['is_external'] ?? false),
            'external_url' => $data['external_url'] ?? null,
        ];
    }
}
