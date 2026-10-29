<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\AdminStatus;
use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Models\Role;
use App\Support\SaltHasher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin management service/管理员管理服务
 *
 * Handles CRUD, password reset, status toggle and role binding for admins
 * 处理管理员 CRUD、密码重置、启停与角色绑定
 */
class AdminService
{
    /**
     * List admins with pagination (eager-load roles)/管理员分页列表（预加载角色）
     *
     * @param  array{page?: int, page_size?: int, status?: int, keyword?: string}  $params
     * @return array{list: array<int, Admin>, total: int, page: int, page_size: int}
     */
    public function index(array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $pageSize = min(max(1, (int) ($params['page_size'] ?? 15)), 100);

        $query = Admin::query()->with(['roles:id,snow_id,name,code']);

        // Filter by status if provided/按状态筛选
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int) $params['status']);
        }

        // Keyword search by username/按用户名关键词搜索
        if (! empty($params['keyword'])) {
            $query->where('username', 'like', '%' . $params['keyword'] . '%');
        }

        $paginator = $query->orderByDesc('id')->paginate($pageSize, ['*'], 'page', $page);

        return $this->formatPaginate($paginator, $page, $pageSize);
    }

    /**
     * Get current admin profile with roles/获取当前管理员资料（含角色）
     */
    public function profile(int $adminId): Admin
    {
        $admin = Admin::query()->with(['roles:id,snow_id,name,code'])->find($adminId);
        if (! $admin) {
            throw ApiException::notFound('api.admin.not_found');
        }

        return $admin;
    }

    /**
     * Change own password: verify old password then set new one/修改自身密码：校验原密码后设置新密码
     */
    public function changePassword(int $adminId, string $oldPassword, string $newPassword): void
    {
        $admin = Admin::query()->find($adminId);
        if (! $admin) {
            throw ApiException::notFound('api.admin.not_found');
        }

        // Verify old password (Hash(明文+salt))/校验原密码（Hash(明文+盐)）
        if (! SaltHasher::check($oldPassword, $admin->salt, (string) $admin->password)) {
            throw new ApiException(ErrorCode::BUSINESS, 'api.password.old_incorrect');
        }

        // Assign new password (auto-hashed by model cast)/赋值新密码（模型 cast 自动哈希）
        $admin->password = $newPassword;
        $admin->save();
    }

    /**
     * Create admin and bind roles in a transaction/创建管理员并绑定角色（事务）
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Admin
    {
        return DB::transaction(function () use ($data): Admin {
            $admin = Admin::query()->create([
                'username' => $data['username'],
                'nickname' => $data['nickname'] ?? null,
                'email' => $data['email'] ?? null,
                // salt 必须先于 password：password 的 cast set 需读取本行 salt 才能加盐哈希
                'salt' => SaltHasher::generate(),
                'password' => $data['password'],
                'avatar' => $data['avatar'] ?? null,
                'status' => isset($data['status']) ? (int) $data['status'] : AdminStatus::ACTIVE->value,
            ]);

            // Bind roles by snow_id/按 snow_id 绑定角色
            $roleIds = $this->resolveRoleIds($data['role_ids'] ?? []);
            $admin->roles()->sync($roleIds);

            return $admin->load('roles:id,snow_id,name,code');
        });
    }

    /**
     * Update admin info and optionally sync roles/更新管理员资料，可选同步角色
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int $snowId, array $data): Admin
    {
        $admin = $this->findBySnowId($snowId);

        return DB::transaction(function () use ($admin, $data): Admin {
            // Update editable fields only when present/仅更新传入字段
            if (array_key_exists('nickname', $data)) {
                $admin->nickname = $data['nickname'];
            }
            if (array_key_exists('email', $data)) {
                $admin->email = $data['email'];
            }
            if (array_key_exists('avatar', $data)) {
                $admin->avatar = $data['avatar'];
            }
            if (array_key_exists('status', $data)) {
                // Protect last active super-admin when disabling/禁用时保护最后一个启用超管
                if ((int) $data['status'] === AdminStatus::DISABLED->value) {
                    $this->assertNotLastSuperAdmin($admin);
                }
                $admin->status = AdminStatus::from((int) $data['status']);
            }
            $admin->save();

            // Sync roles and clear this admin's caches/同步角色并清理该管理员缓存
            if (array_key_exists('role_ids', $data)) {
                $roleIds = $this->resolveRoleIds($data['role_ids']);
                $admin->roles()->sync($roleIds);
                $this->clearAdminCache($admin->id);
            }

            return $admin->load('roles:id,snow_id,name,code');
        });
    }

    /**
     * Reset another admin's password/重置其他管理员密码
     */
    public function resetPassword(int $snowId, string $newPassword): void
    {
        $admin = $this->findBySnowId($snowId);
        $admin->password = $newPassword;
        $admin->save();
    }

    /**
     * Toggle admin status/启用禁用管理员
     */
    public function updateStatus(int $snowId, int $status): Admin
    {
        $admin = $this->findBySnowId($snowId);

        if ($status === AdminStatus::DISABLED->value) {
            $this->assertNotLastSuperAdmin($admin);
        }

        $admin->status = AdminStatus::from($status);
        $admin->save();

        return $admin;
    }

    /**
     * Soft delete admin/软删除管理员
     */
    public function destroy(int $snowId): void
    {
        $admin = $this->findBySnowId($snowId);
        $this->assertNotLastSuperAdmin($admin);

        $admin->delete(); // SoftDeletes/软删除
        $this->clearAdminCache($admin->id);
    }

    /**
     * Find admin by snow_id or throw not_found/按 snow_id 查找管理员，不存在抛异常
     */
    private function findBySnowId(int $snowId): Admin
    {
        $admin = Admin::query()->where('snow_id', $snowId)->first();
        if (! $admin) {
            throw ApiException::notFound('api.admin.not_found');
        }

        return $admin;
    }

    /**
     * Resolve role snow_ids to internal primary keys/将角色 snow_id 解析为内部主键
     *
     * @param  array<int, int>  $roleSnowIds
     * @return array<int, int>
     */
    private function resolveRoleIds(array $roleSnowIds): array
    {
        if (empty($roleSnowIds)) {
            return [];
        }

        return Role::query()->whereIn('snow_id', $roleSnowIds)->pluck('id')->all();
    }

    /**
     * Clear one admin's menu tree and permission cache/清理指定管理员的菜单树与权限缓存
     */
    private function clearAdminCache(int $adminId): void
    {
        Cache::forget("admin_menu:tree:{$adminId}");
        Cache::forget("admin:permissions:{$adminId}");
    }

    /**
     * Guard: cannot disable or delete the last active super-admin/
     * 保护逻辑：不允许停用或删除最后一个启用的超管
     */
    private function assertNotLastSuperAdmin(Admin $admin): void
    {
        $isSuper = $admin->roles()->where('code', 'super-admin')->exists();
        if (! $isSuper) {
            return;
        }

        $activeSuperCount = Admin::query()
            ->where('status', AdminStatus::ACTIVE->value)
            ->whereHas('roles', function ($q) {
                $q->where('code', 'super-admin');
            })
            ->count();

        if ($activeSuperCount <= 1) {
            throw new ApiException(ErrorCode::FORBIDDEN, 'api.common.biz_error');
        }
    }

    /**
     * Format paginator into {list,total,page,page_size}/统一分页返回结构
     *
     * @return array{list: array<int, Admin>, total: int, page: int, page_size: int}
     */
    private function formatPaginate(LengthAwarePaginator $paginator, int $page, int $pageSize): array
    {
        return [
            'list' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }
}
