<?php

declare(strict_types=1);

namespace App\Models;

use App\Base\BaseModel;
use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Product model/商品业务模型
 *
 * Demo business module: C-end read-only, admin full CRUD
 * 示例业务模块：C 端只读，管理员完整增删改查
 */
class Product extends BaseModel
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable/可批量赋值的字段
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'desc',
        'price',
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
            // Price as decimal with 2 places/价格保留两位小数
            'price' => 'decimal:2',
            // On-shelf status as BackedEnum/上下架状态使用 BackedEnum 枚举
            'status' => ProductStatus::class,
        ];
    }
}
