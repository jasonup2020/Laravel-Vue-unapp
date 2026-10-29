<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Support\SaltHasher;

/**
 * C-end user profile service/C 端个人中心服务.
 */
class UserService
{
    /**
     * Get user profile/获取用户个人信息.
     */
    public function profile(int $userId): User
    {
        // Find user, 404 if missing/查找用户，不存在 → 404
        $user = User::query()->find($userId);

        if (! $user) {
            throw new ApiException(ErrorCode::NOT_FOUND, 'api.user.not_found');
        }

        return $user;
    }

    /**
     * Update user profile (name only)/更新用户资料（仅允许 name）.
     *
     * @param array{name?: string} $data
     */
    public function updateProfile(int $userId, array $data): User
    {
        $user = $this->profile($userId);

        // Only update allowed field name/只更新允许字段 name
        if (array_key_exists('name', $data)) {
            $user->name = $data['name'];
        }

        $user->save();

        return $user;
    }

    /**
     * Change password: verify old password then update (hashed cast auto-hashes)/
     * 修改密码：校验原密码后更新（password cast=hashed 自动哈希）.
     */
    public function changePassword(int $userId, string $oldPassword, string $newPassword): void
    {
        $user = $this->profile($userId);

        // Old password incorrect (Hash(明文+salt))/原密码错误（Hash(明文+盐)）
        if (! SaltHasher::check($oldPassword, $user->salt, (string) $user->password)) {
            throw new ApiException(ErrorCode::BAD_REQUEST, 'api.password.old_incorrect');
        }

        // Set new password (auto-hashed by cast)/赋值新密码（hashed cast 自动加密）
        $user->password = $newPassword;
        $user->save();
    }
}
