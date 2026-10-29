<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Rate limit rule model/限流规则模型
 *
 * Stores dynamic rate-limit rules consumed by the api.composite.rate middleware.
 * Extends Eloquent Model directly (not BaseModel) — internal table with no
 * snow_id column and no deleted_at column.
 * 存储 api.composite.rate 中间件消费的动态限流规则。
 * 直接继承 Eloquent Model（非 BaseModel）——内部表无 snow_id 列、无 deleted_at 列。
 */
class RateLimitRule extends Model
{
    /**
     * The attributes that are mass assignable/可批量赋值的字段
     *
     * @var list<string>
     */
    protected $fillable = [
        'rule_key',
        'max_attempts',
        'decay_seconds',
        'enabled',
        'remark',
    ];

    /**
     * The attributes that should be hidden for serialization/序列化时隐藏的字段
     *
     * Deliberately empty: this is internal admin-facing data and the API uses
     * the auto-increment id as the resource identifier (no snow_id on this
     * table per contract §3 row for rate_limit_rules).
     * 故意留空：本表为内部管理数据，接口直接使用自增 id 作为资源标识
     * （按契约第3节 rate_limit_rules 行定义，本表无 snow_id）。
     *
     * @var list<string>
     */
    protected $hidden = [];

    /**
     * Get the attributes that should be cast/获取属性类型转换映射
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Treat enabled as boolean/将 enabled 转为布尔值
            'enabled'        => 'boolean',
            // Cast integer columns to int/将整数字段转为 int
            'max_attempts'   => 'integer',
            'decay_seconds'  => 'integer',
        ];
    }
}
