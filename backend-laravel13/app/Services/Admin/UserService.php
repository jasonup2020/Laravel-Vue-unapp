<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\User\MultiDeviceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * C-end user management service (admin side)/普通用户管理服务（管理员视角）
 *
 * Handles user listing, detail, status toggle, online device count and force kick
 * 处理用户列表、详情、启停、在线终端数与强制踢下线
 */
class UserService
{
    public function __construct(private MultiDeviceService $multiDevice)
    {
    }

    /**
     * List C-end users with pagination/普通用户分页列表
     *
     * @param  array{page?: int, page_size?: int, status?: int, keyword?: string}  $params
     * @return array{list: array<int, User>, total: int, page: int, page_size: int}
     */
    public function index(array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $pageSize = min(max(1, (int) ($params['page_size'] ?? 15)), 100);

        $query = User::query();

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int) $params['status']);
        }

        if (! empty($params['keyword'])) {
            $query->where(function ($q) use ($params): void {
                $q->where('name', 'like', '%' . $params['keyword'] . '%')
                    ->orWhere('email', 'like', '%' . $params['keyword'] . '%');
            });
        }

        $paginator = $query->orderByDesc('id')->paginate($pageSize, ['*'], 'page', $page);

        return [
            'list' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * Get user detail by snow_id/按 snow_id 获取用户详情
     */
    public function show(int $snowId): User
    {
        return $this->findBySnowId($snowId);
    }

    /**
     * Toggle user status/启用禁用用户
     */
    public function updateStatus(int $snowId, int $status): User
    {
        $user = $this->findBySnowId($snowId);
        $user->status = UserStatus::from($status);
        $user->save();

        return $user;
    }

    /**
     * Count online devices of a user/查看用户在线终端数
     */
    public function online(int $snowId): int
    {
        $user = $this->findBySnowId($snowId);

        return $this->multiDevice->count($user->id);
    }

    /**
     * Force kick all devices of a user (high risk)/强制踢出用户全部终端（高危）
     */
    public function kick(int $snowId): void
    {
        $user = $this->findBySnowId($snowId);
        $this->multiDevice->kickAll($user->id);
    }

    /**
     * Find user by snow_id or throw/按 snow_id 查找用户
     */
    private function findBySnowId(int $snowId): User
    {
        $user = User::query()->where('snow_id', $snowId)->first();
        if (! $user) {
            throw ApiException::notFound('api.user.not_found');
        }

        return $user;
    }
}
