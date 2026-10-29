<?php

declare(strict_types=1);

namespace App\Models;

use App\Base\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Role model/RBAC角色模型
 *
 * Represents admin roles in the RBAC system
 * RBAC 权限体系中的管理员角色
 */
class Role extends BaseModel
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable/可批量赋值的字段
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'remark',
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
    ];

    /**
     * Permissions granted to this role/角色拥有的权限
     *
     * Many-to-many via pivot table role_permission
     * 通过中间表 role_permission 多对多关联
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission', 'role_id', 'permission_id');
    }

    /**
     * Menus assigned to this role/角色绑定的菜单
     *
     * Many-to-many via pivot table role_menu
     * 通过中间表 role_menu 多对多关联
     */
    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(AdminMenu::class, 'role_menu', 'role_id', 'menu_id');
    }

    /**
     * Admins holding this role/拥有此角色的管理员
     *
     * Many-to-many via pivot table admin_role
     * 通过中间表 admin_role 多对多关联
     */
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class, 'admin_role', 'role_id', 'admin_id');
    }
}
