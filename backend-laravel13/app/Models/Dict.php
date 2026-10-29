<?php

declare(strict_types=1);

namespace App\Models;

use App\Base\BaseModel;
use App\Enums\DictStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Dict model/通用数据字典模型
 *
 * Key-value dictionary for frontend option lists
 * 前端选项列表使用的通用键值字典
 */
class Dict extends BaseModel
{
    /** @use HasFactory<\Database\Factories\DictFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable/可批量赋值的字段
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'label',
        'label_en',
        'value',
        'sort',
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
    ];

    /**
     * Get the attributes that should be cast/获取属性类型转换映射
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Enabled status as BackedEnum/启用状态使用 BackedEnum 枚举
            'status' => DictStatus::class,
        ];
    }
}
