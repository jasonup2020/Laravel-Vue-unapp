<?php

declare(strict_types=1);

namespace App\Models;

use App\Base\BaseModel;

/**
 * AdminOperationLog model/管理员操作日志模型
 *
 * Audit log for admin write operations: URL, method, IP, params, cost, risk
 * 管理员写操作审计日志：URL、方法、IP、参数、耗时、高危标记
 *
 * Table has created_at only, no updated_at column
 * 数据表仅有 created_at 列，无 updated_at
 */
class AdminOperationLog extends BaseModel
{

    /**
     * The table associated with the model/模型对应的数据表
     *
     * @var string
     */
    protected $table = 'admin_operation_logs';

    /**
     * Indicates if the model should be timestamped/是否自动维护时间戳
     *
     * Disabled because the table has no updated_at column;
     * created_at is manually stamped in the creating event
     * 因表无 updated_at 列而关闭；created_at 在 creating 事件中手动赋值
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable/可批量赋值的字段
     *
     * @var list<string>
     */
    protected $fillable = [
        'admin_id',
        'url',
        'method',
        'ip',
        'request_params',
        'response_result',
        'cost_ms',
        'is_high_risk',
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
            // High-risk operation flag/高危操作标记
            'is_high_risk' => 'boolean',
        ];
    }

    /**
     * Boot the model/启动模型
     *
     * Manually stamp created_at since $timestamps is disabled
     * 因 $timestamps 关闭，在 creating 事件手动写入 created_at
     */
    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            // Set created_at on insert because updated_at column does not exist
            // 插入时手动设置 created_at，因数据表无 updated_at 列
            $model->created_at ??= now();
        });
    }
}
