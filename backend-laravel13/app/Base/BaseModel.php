<?php

declare(strict_types=1);

namespace App\Base;

use App\Models\Traits\HasSnowId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base Eloquent model/模型基类
 *
 * Provides: HasSnowId (auto-fill snow_id on creating), hides auto-increment id
 * from serialization, attributeComments() for docs/codegen, and a template
 * scopeActive(). SoftDeletes is NOT applied here — subclasses that need it
 * must explicitly `use Illuminate\Database\Eloquent\SoftDeletes;` in their own
 * class body. This avoids SQL errors on tables without a deleted_at column
 * (e.g. dicts, permissions, notices when no soft-deletes flag).
 * 提供：HasSnowId（creating 时自动填充 snow_id）、序列化隐藏自增 id、
 * attributeComments() 供文档/生成器、模板 scopeActive()。
 * 本基类不再承载 SoftDeletes——需要软删除的子类必须在自己类体中显式
 * `use Illuminate\Database\Eloquent\SoftDeletes;`。这样无 deleted_at 列的表
 * （如 dicts、permissions、未加 --soft-deletes 的模块）不会被全局 scope 误加
 * `where deleted_at is null` 导致 SQL 报错。
 */
abstract class BaseModel extends Model
{
    use HasSnowId;

    /**
     * The attributes that should be hidden for serialization/序列化时隐藏的属性
     *
     * @var list<string>
     */
    protected $hidden = [
        'id',
    ];

    /**
     * Field comments mirroring migration ->comment() values/字段注释映射
     *
     * Returned as [column => 'English/中文'] so docs/codegen can render them.
     * 返回 [列名 => 'English/中文']，供文档与代码生成器渲染。
     *
     * @return array<string,string>
     */
    public function attributeComments(): array
    {
        return [];
    }

    /**
     * Scope: active only (status = 1)/作用域：仅启用（status = 1）
     *
     * Template scope; subclasses may override to match their status enum.
     * 模板作用域；子类可覆写为自己的状态枚举。
     *
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 1);
    }

    // ---------------------------------------------------------------------
    // SoftDeletes usage/软删除用法
    // ---------------------------------------------------------------------
    // Subclasses that need soft deletes must opt in explicitly:
    // 需要软删除的子类必须显式引入：
    //
    //     use Illuminate\Database\Eloquent\SoftDeletes;
    //
    //     class Product extends BaseModel { use SoftDeletes; ... }
    //
    // Tables without deleted_at column (dicts, permissions, etc.) must NOT
    // add the trait, otherwise Eloquent will append `where deleted_at is null`
    // and the query will fail.
    // 无 deleted_at 列的表（dicts、permissions 等）不要加该 trait，
    // 否则 Eloquent 会自动追加 `where deleted_at is null` 导致 SQL 报错。

    // ---------------------------------------------------------------------
    // Relation placeholders/关联占位
    // ---------------------------------------------------------------------
    // Subclasses should define relations here, e.g.:
    // 子类应在此定义关联，例如：
    //
    // public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    // {
    //     return $this->belongsTo(Product::class, 'product_id', 'id');
    // }
}
