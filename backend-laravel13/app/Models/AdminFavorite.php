<?php

declare(strict_types=1);

namespace App\Models;

use App\Base\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * AdminFavorite model/管理员侧收藏查询模型
 *
 * Full-isolation extension template: points to the same physical table
 * as UserFavorite but defines admin-side scopes/relations independently
 * 全隔离扩展示例：与 UserFavorite 指向同一物理表，管理员侧独立定义关联
 */
class AdminFavorite extends BaseModel
{
    use SoftDeletes;

    /**
     * The table associated with the model/模型对应的数据表
     *
     * Shares the same physical table as UserFavorite
     * 与 UserFavorite 共享同一物理表
     *
     * @var string
     */
    protected $table = 'user_favorites';

    /**
     * The attributes that are mass assignable/可批量赋值的字段
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'product_id',
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
     * The product of this favorite/收藏对应的商品
     *
     * Belongs-to relation via product_id
     * 通过 product_id 多对一关联
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    /**
     * The user who owns this favorite/收藏所属的用户
     *
     * Belongs-to relation via user_id (admin-side view)
     * 通过 user_id 多对一关联（管理员视角）
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
