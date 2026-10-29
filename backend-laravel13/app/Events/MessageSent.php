<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\MessageReceiverType;
use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * In-app message sent event/站内消息发送事件.
 *
 * Broadcast to the channel matched by receiver_type; compatible with Reverb +
 * laravel-echo/pusher-js subscribe handshake/按 receiver_type 广播到对应频道，
 * 兼容 Reverb + laravel-echo/pusher-js 订阅握手.
 */
class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  Message $message persisted message model/已持久化的消息模型
     */
    public function __construct(public Message $message)
    {
    }

    /**
     * Channels this event broadcasts to/本事件广播的频道.
     *
     * Mapping by receiver_type/按接收类型映射频道：
     * - user     -> private-user.{receiver_id}  (private, auth via api guard)/私有频道，api 守卫鉴权
     * - admin    -> private-admin.{receiver_id} (private, auth via admin-api guard)/私有频道，admin-api 守卫鉴权
     * - all_user -> public-system-announce     (public, no auth)/公共频道，无需鉴权
     * - all_admin-> public-system-announce     (public, no specific admin target)/公共频道，无指定管理员
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Resolve enum value (model casts receiver_type)/读取枚举值（模型已 cast）
        $type = $this->message->receiver_type;

        return match ($type) {
            // Targeted user private channel/定向用户私有频道
            MessageReceiverType::USER => [new PrivateChannel('private-user.' . (int) $this->message->receiver_id)],
            // Targeted admin private channel/定向管理员私有频道
            MessageReceiverType::ADMIN => [new PrivateChannel('private-admin.' . (int) $this->message->receiver_id)],
            // Broadcast to public system announcement channel/全体广播走公共公告频道
            MessageReceiverType::ALL_USER, MessageReceiverType::ALL_ADMIN => [new Channel('public-system-announce')],
        };
    }

    /**
     * Broadcast event name consumed by the frontend/前端订阅的广播事件名.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Data payload pushed over the wire/推送给前端的数据载荷.
     *
     * Carries outward snow_id (never auto-increment id); msg_type mapped to
     * message_type for the client/仅携带对外 snow_id（不暴露自增 id）；msg_type 输出为 message_type.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'snow_id'      => $this->message->snow_id,
            'title'        => $this->message->title,
            'content'      => $this->message->content,
            // Column msg_type exposed as message_type/字段 msg_type 对外输出为 message_type
            'message_type' => $this->message->msg_type?->value ?? $this->message->msg_type,
            'created_at'   => $this->message->created_at?->toIso8601String(),
        ];
    }
}
