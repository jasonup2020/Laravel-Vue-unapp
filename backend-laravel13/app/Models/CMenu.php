<?php

declare(strict_types=1);

namespace App\Models;

use App\Base\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * C-end menu model/C端菜单模型
 *
 * Represents public-facing navigation menus displayed to C-end users
 * C 端用户可见的公开导航菜单
 */
class CMenu extends BaseModel
{
    /** @use HasFactory<\Database\Factories\CMenuFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable/可批量赋值的字段
     *
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'title',
        'title_en',
        'icon',
        'path',
        'url',
        'sort',
        'is_show',
    ];

    /**
     * Get the attributes that should be cast/获取属性类型转换映射
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Visible in C-end flag/C端是否显示
            'is_show' => 'boolean',
        ];
    }

    /**
     * Child menus/子菜单
     *
     * Self-referential via parent_id/通过 parent_id 自关联
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id');
    }
}
