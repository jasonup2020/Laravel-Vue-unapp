<?php

declare(strict_types=1);

namespace App\Models;

use App\Base\BaseModel;

/**
 * Permission model/权限模型
 *
 * Represents action-level permissions in RBAC: resource × action
 * RBAC 体系中的动作级权限：资源 × 动作
 */
class Permission extends BaseModel
{

    /**
     * The attributes that are mass assignable/可批量赋值的字段
     *
     * action: list/create/update/delete or custom (export/kick/...)
     * action：list/create/update/delete 或自定义（export/kick 等）
     * type: 1=system preset, 2=custom
     * type：1=系统预置，2=自定义
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'group',
        'action',
        'type',
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
     * Get the attributes that should be cast/获取属性类型转换映射
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Permission type: 1=system, 2=custom/权限类型：1=系统预置，2=自定义
            'type' => 'integer',
        ];
    }
}
