<?php

declare(strict_types=1);

namespace App\Models;

use App\Base\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * AdminMenu model/后台动态菜单模型
 *
 * Represents configurable sidebar menus for the admin panel
 * 后台管理面板可配置的侧边栏菜单
 */
class AdminMenu extends BaseModel
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable/可批量赋值的字段
     *
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'title',
        'path',
        'component',
        'icon',
        'permission_code',
        'sort',
        'is_show',
        'is_cache',
        'is_external',
        'external_url',
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
            // Show in sidebar flag/是否侧边栏显示
            'is_show' => 'boolean',
            // Keep-alive cache flag/是否 keep-alive 缓存
            'is_cache' => 'boolean',
            // External link flag/是否外部链接
            'is_external' => 'boolean',
        ];
    }

    /**
     * Child menus of this menu/此菜单的子菜单
     *
     * Self-referential relation via parent_id
     * 通过 parent_id 自关联
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id');
    }
}
