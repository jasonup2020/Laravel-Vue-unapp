<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdminStatus;
use App\Models\Casts\SaltedPasswordCast;
use App\Models\Traits\HasSnowId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

/**
 * Admin model/后台管理员模型
 *
 * Represents backend admins authenticated via JWT guard 'admin-api'
 * 通过 JWT 守卫 admin-api 认证的后台管理员
 */
class Admin extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\AdminFactory> */
    use HasFactory, SoftDeletes, HasSnowId;

    /**
     * The attributes that are mass assignable/可批量赋值的字段
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'nickname',
        'email',
        'password',
        'salt',
        'avatar',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization/序列化时隐藏的字段
     *
     * Hide auto-increment id; only snow_id is exposed externally
     * 隐藏自增 id，对外仅暴露 snow_id
     *
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'password',
        'salt',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast/获取属性类型转换映射
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Status as BackedEnum/状态使用 BackedEnum 枚举
            'status' => AdminStatus::class,
            // Salted-hash password: Hash(明文+salt)/加盐哈希：Hash(明文+盐)
            'password' => SaltedPasswordCast::class,
        ];
    }

    /**
     * Get the identifier stored in JWT subject claim/获取JWT subject声明中的标识符
     *
     * Returns the auto-increment id as JWT subject
     * 返回自增 id 作为 JWT subject
     */
    public function getJWTIdentifier(): int
    {
        return $this->id;
    }

    /**
     * Get custom JWT claims/获取自定义JWT声明
     *
     * Adds guard='admin-api' to distinguish admin tokens
     * 携带 guard=admin-api 标识管理员令牌
     *
     * @return array<string, string>
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'guard' => 'admin-api',
        ];
    }

    /**
     * The roles assigned to this admin/管理员拥有的角色
     *
     * Many-to-many via pivot table admin_role
     * 通过中间表 admin_role 多对多关联
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'admin_role', 'admin_id', 'role_id');
    }
}
