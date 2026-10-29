<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\AdminStatus;
use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Models\Permission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Permission management service/权限管理服务
 *
 * Handles action-level permission CRUD with grouped listing and cache invalidation
 * 处理动作级权限 CRUD、按组列出与缓存失效
 */
class PermissionService
{
    /** System preset type/系统预置类型 */
    private const TYPE_SYSTEM = 1;

    /**
     * List all permissions grouped by group/按 group 分组列出全部权限
     *
     * @return array{list: Collection<int, Permission>, groups: array<string, array<int, array<string, mixed>>>}
     */
    public function index(): array
    {
        $permissions = Permission::query()
            ->orderBy('group')
            ->orderBy('id')
            ->get();

        // Format each permission with bilingual label/逐项格式化含双语 label
        $formatted = $permissions->map(fn (Permission $p) => $this->formatPermission($p))->all();

        // Group by `group` field for assignment UI/按 group 字段分组供分配 UI
        $groups = collect($formatted)->groupBy(fn ($p) => $p['group'] ?? 'default')
            ->map(fn ($items) => $items->values()->all())
            ->toArray();

        return [
            'list' => $formatted,
            'groups' => $groups,
        ];
    }

    /**
     * Create a custom permission/创建自定义权限
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): array
    {
        $permission = new Permission();
        $permission->name = $data['name'];
        $permission->label_en = $data['label_en'] ?? null;
        $permission->code = $data['code'];
        $permission->group = $data['group'];
        $permission->action = $data['action'];
        $permission->type = (int) $data['type'];
        $permission->remark = $data['remark'] ?? null;
        $permission->save();

        // Invalidate all admins' permission cache/清理全部管理员权限缓存
        $this->clearAllPermissionCache();

        return $this->formatPermission($permission);
    }

    /**
     * Update a permission/更新权限
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int $snowId, array $data): array
    {
        $permission = $this->findBySnowId($snowId);

        // Only update provided fields/仅更新传入字段
        if (array_key_exists('name', $data)) {
            $permission->name = $data['name'];
        }
        if (array_key_exists('label_en', $data)) {
            $permission->label_en = $data['label_en'];
        }
        if (array_key_exists('code', $data)) {
            $permission->code = $data['code'];
        }
        if (array_key_exists('group', $data)) {
            $permission->group = $data['group'];
        }
        if (array_key_exists('action', $data)) {
            $permission->action = $data['action'];
        }
        if (array_key_exists('type', $data)) {
            $permission->type = (int) $data['type'];
        }
        if (array_key_exists('remark', $data)) {
            $permission->remark = $data['remark'];
        }
        $permission->save();

        $this->clearAllPermissionCache();

        return $this->formatPermission($permission);
    }

    /**
     * Delete a custom permission; system preset (type=1) cannot be deleted/
     * 删除自定义权限；系统预置（type=1）不可删
     */
    public function delete(int $snowId): void
    {
        $permission = $this->findBySnowId($snowId);

        // System preset permissions are protected/系统预置权限受保护不可删
        if ((int) $permission->type === self::TYPE_SYSTEM) {
            throw new ApiException(ErrorCode::BUSINESS, 'api.permission.system_not_deletable');
        }

        $permission->delete();
        $this->clearAllPermissionCache();
    }

    /**
     * Find permission by snow_id or throw/按 snow_id 查找权限
     */
    private function findBySnowId(int $snowId): Permission
    {
        $permission = Permission::query()->where('snow_id', $snowId)->first();
        if (! $permission) {
            throw ApiException::notFound('api.permission.not_found');
        }

        return $permission;
    }

    /**
     * Format permission model into array with bilingual label/
     * 格式化权限模型为含双语 label 的数组
     * Keeps `name` as plain string for backward compat/保留 name 原字符串兼容现有调用
     *
     * @return array<string, mixed>
     */
    private function formatPermission(Permission $permission): array
    {
        $arr = $permission->toArray();
        // Bilingual label object for UI display/双语 label 对象供界面展示
        $arr['label'] = [
            'zh-CN' => $permission->name,
            'en' => $permission->label_en ?? $permission->name,
        ];
        unset($arr['label_en']);

        return $arr;
    }

    /**
     * Clear admin:permissions:{adminId} cache for every active admin/
     * 遍历全部启用管理员，逐个清理权限缓存
     */
    private function clearAllPermissionCache(): void
    {
        $adminIds = Admin::query()
            ->where('status', AdminStatus::ACTIVE->value)
            ->pluck('id')
            ->all();

        foreach ($adminIds as $adminId) {
            Cache::forget("admin:permissions:{$adminId}");
        }
    }
}
