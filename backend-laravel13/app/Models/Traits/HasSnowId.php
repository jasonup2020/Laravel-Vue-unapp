<?php

declare(strict_types=1);

namespace App\Models\Traits;

/**
 * Snowflake ID trait/雪花ID特质
 *
 * Automatically generates snow_id on model creating event
 * 自动在模型 creating 事件生成雪花 ID
 */
trait HasSnowId
{
    /**
     * Boot the snow_id trait/启动雪花ID特质
     *
     * Hooks into the creating event to auto-fill snow_id
     * 挂钩 creating 事件自动填充 snow_id
     */
    protected static function bootHasSnowId(): void
    {
        static::creating(function ($model): void {
            // Auto-generate snow_id if not already set/若未设置则自动生成雪花ID
            $model->snow_id ??= \snow_id();
        });
    }
}
