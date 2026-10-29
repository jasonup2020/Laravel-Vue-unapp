<?php

declare(strict_types=1);

namespace App\Models;

use App\Base\BaseModel;
use App\Enums\MessageReceiverType;
use App\Enums\MessageType;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Message model/站内即时消息模型
 *
 * Persisted messages for Reverb WebSocket push
 * Reverb WebSocket 推送的持久化站内消息
 */
class Message extends BaseModel
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable/可批量赋值的字段
     *
     * @var list<string>
     */
    protected $fillable = [
        'receiver_type',
        'receiver_id',
        'msg_type',
        'title',
        'content',
        'extra',
        'is_read',
        'is_pop',
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
            // Extra JSON as array/扩展参数 JSON 转数组
            'extra' => 'array',
            // Receiver type as BackedEnum/接收人类型使用 BackedEnum 枚举
            'receiver_type' => MessageReceiverType::class,
            // Message type as BackedEnum/消息类型使用 BackedEnum 枚举
            'msg_type' => MessageType::class,
            // Read flag/已读标记
            'is_read' => 'boolean',
            // Popup flag/客户端弹窗标记
            'is_pop' => 'boolean',
        ];
    }
}
