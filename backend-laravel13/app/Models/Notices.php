<?php

declare(strict_types=1);

namespace App\Models;

use App\Base\BaseModel;

/**
 * Notices model/通知公告模型
 *
 * Extends BaseModel (which provides HasSnowId and hidden id).
 * No SoftDeletes trait — notices table has no deleted_at column.
 * 继承 BaseModel（提供 HasSnowId 和隐藏 id）。
 * 不使用 SoftDeletes trait——notices 表无 deleted_at 列。
 */
class Notices extends BaseModel
{

    /**
     * The attributes that are mass assignable/可批量赋值字段
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'title',
        'status',
        'remark',
    ];

    /**
     * The attributes that should be hidden for serialization/序列化时隐藏的属性
     *
     * @var list<string>
     */
    protected $hidden = [
        'id',
    ];

    /**
     * Field comments mirroring migration ->comment()/字段注释（与迁移 comment 呼应）
     *
     * @return array<string,string>
     */
    public function attributeComments(): array
    {
        return [
            'name'   => 'Name/名称',
            'title'  => 'Title/标题',
            'status' => 'Status 1=enabled 0=disabled/状态 1=启用 0=禁用',
            'remark' => 'Remark/备注',
        ];
    }
}
