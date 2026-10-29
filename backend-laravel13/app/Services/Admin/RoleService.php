<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Role management service/角色管理服务
 *
 * Handles role CRUD, permission assignment and menu assignment
 * 处理角色 CRUD、权限分配与菜单分配
 */
class RoleService
{
    /**
     * List roles with pagination (with permission/menu counts)/角色分页列表（含权限/菜单数量）
     *
     * @param  array{page?: int, page_size?: int}  $params
     * @return array{list: LengthAwarePaginator, total: int, page: int, page_size: int}
     */
    public function index(array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $pageSize = min(max(1, (int) ($params['page_size'] ?? 15)), 100);

        $paginator = Role::query()
            ->with(['permissions:id,snow_id,code', 'menus:id,snow_id'])
            ->withCount(['permissions', 'menus', 'admins'])
            ->orderByDesc('id')
            ->paginate($pageSize, ['*'], 'page', $page);

        return [
            'list' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * Create role/创建角色
     *
     * @param  array{name?: string, code?: string, remark?: string|null}  $data
     */
    public function create(array $data): Role
    {
        $code = $data['code'] ?? '';

        // Reuse a soft-deleted role holding this code: restore it and apply the
        // new fields, so the DB unique index keeps working after soft delete.
        // 若存在软删的同 code 角色，恢复该记录并应用新字段，避免唯一索引在软删后占用 key
        $trashed = Role::withTrashed()->where('code', $code)->first();
        if ($trashed !== null) {
            $trashed->restore();
            $trashed->name = $data['name'];
            $trashed->remark = $data['remark'] ?? null;
            $trashed->save();

            return $trashed;
        }

        // Check code uniqueness/校验 code 唯一
        $exists = Role::query()->where('code', $code)->exists();
        if ($exists) {
            throw new ApiException(ErrorCode::BUSINESS, 'api.role.code_exists');
        }

        return Role::query()->create([
            'name' => $data['name'],
            'code' => $code,
            'remark' => $data['remark'] ?? null,
        ]);
    }

    /**
     * Update role (name/remark only; code is immutable)/更新角色（仅名称/备注，code 不可改）
     *
     * @param  array{name?: string, remark?: string|null}  $data
     */
    public function update(int $snowId, array $data): Role
    {
        $role = $this->findBySnowId($snowId);

        if (array_key_exists('name', $data)) {
            $role->name = $data['name'];
        }
        if (array_key_exists('remark', $data)) {
            $role->remark = $data['remark'];
        }
        $role->save();

        return $role;
    }

    /**
     * Delete role: detach all relations then soft delete/删除角色：解绑关联后软删
     */
    public function destroy(int $snowId): void
    {
        $role = $this->findBySnowId($snowId);

        DB::transaction(function () use ($role): void {
            // Detach admins, permissions, menus from pivot tables/解绑管理员、权限、菜单中间表
            $role->admins()->detach();
            $role->permissions()->detach();
            $role->menus()->detach();

            $role->delete(); // SoftDeletes/软删除
        });
    }

    /**
     * Sync permissions to role and clear permission cache for all admins under this role/
     * 同步角色权限，并清理该角色下全部管理员的权限缓存
     *
     * @param  array<int, int>  $permissionSnowIds
     */
    public function assignPermissions(int $snowId, array $permissionSnowIds): void
    {
        $role = $this->findBySnowId($snowId);

        DB::transaction(function () use ($role, $permissionSnowIds): void {
            // Resolve permission snow_ids to internal ids/将权限 snow_id 解析为内部主键
            $permissionIds = Permission::query()
                ->whereIn('snow_id', $permissionSnowIds)
                ->pluck('id')
                ->all();

            $role->permissions()->sync($permissionIds);
        });

        // Clear permission cache for all admins under this role/清理该角色下全部管理员权限缓存
        $this->clearRoleAdminsPermissionCache($role->id);
    }

    /**
     * Sync menus to role and clear menu cache for all admins under this role/
     * 同步角色菜单，并清理该角色下全部管理员的菜单缓存
     *
     * @param  array<int, int>  $menuSnowIds
     */
    public function assignMenus(int $snowId, array $menuSnowIds): void
    {
        $role = $this->findBySnowId($snowId);

        DB::transaction(function () use ($role, $menuSnowIds): void {
            // Resolve menu snow_ids to internal ids/将菜单 snow_id 解析为内部主键
            $menuIds = \App\Models\AdminMenu::query()
                ->whereIn('snow_id', $menuSnowIds)
                ->pluck('id')
                ->all();

            $role->menus()->sync($menuIds);
        });

        // Clear menu cache for all admins under this role/清理该角色下全部管理员菜单缓存
        $this->clearRoleAdminsMenuCache($role->id);
    }

    /**
     * Find role by snow_id or throw/按 snow_id 查找角色
     */
    private function findBySnowId(int $snowId): Role
    {
        $role = Role::query()->where('snow_id', $snowId)->first();
        if (! $role) {
            throw ApiException::notFound('api.role.not_found');
        }

        return $role;
    }

    /**
     * Clear permission cache (admin:permissions:{adminId}) for every admin under this role/
     * 清理该角色下全部管理员的权限缓存
     */
    private function clearRoleAdminsPermissionCache(int $roleId): void
    {
        $adminIds = DB::table('admin_role')
            ->where('role_id', $roleId)
            ->pluck('admin_id')
            ->all();

        foreach ($adminIds as $adminId) {
            Cache::forget("admin:permissions:{$adminId}");
        }
    }

    /**
     * Clear menu cache (admin_menu:tree:{adminId}) for every admin under this role/
     * 清理该角色下全部管理员的菜单缓存
     */
    private function clearRoleAdminsMenuCache(int $roleId): void
    {
        $adminIds = DB::table('admin_role')
            ->where('role_id', $roleId)
            ->pluck('admin_id')
            ->all();

        foreach ($adminIds as $adminId) {
            Cache::forget("admin_menu:tree:{$adminId}");
        }
    }
}
