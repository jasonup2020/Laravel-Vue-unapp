<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserStatus;
use App\Models\Casts\SaltedPasswordCast;
use App\Models\Traits\HasSnowId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

/**
 * User model/C端普通用户模型
 *
 * Represents C-end ordinary users authenticated via JWT guard 'api'
 * 通过 JWT 守卫 api 认证的 C 端普通用户
 */
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, HasSnowId;

    /**
     * The attributes that are mass assignable/可批量赋值的字段
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'salt',
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
            // Salted-hash password: Hash(明文+salt)/加盐哈希：Hash(明文+盐)
            'password' => SaltedPasswordCast::class,
            // Status as BackedEnum/状态使用 BackedEnum 枚举
            'status' => UserStatus::class,
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
     * Adds guard='api' to distinguish C-end tokens
     * 携带 guard=api 标识 C 端令牌
     *
     * @return array<string, string>
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'guard' => 'api',
        ];
    }
}
