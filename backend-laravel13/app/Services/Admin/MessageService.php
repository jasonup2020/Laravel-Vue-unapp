<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\MessageReceiverType;
use App\Enums\MessageType;
use App\Events\MessageSent;
use App\Models\Message;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Admin-side in-app message service/管理端站内消息服务.
 *
 * Lists admin-facing messages and dispatches broadcasts/
 * 面向 admin-api 守卫管理员处理消息列表与广播派发.
 */
class MessageService
{
    /**
     * Paginate messages visible to admins/分页拉取管理员可见消息.
     *
     * Visible scope = (receiver_type=admin AND receiver_id=me) OR (receiver_type=all_admin)
     *                 OR (messages sent by me, tracked via extra.sender_admin_id).
     * all_admin rows store receiver_id=0 per contract section 3.
     * 可见范围 = (定向本人) OR (全体管理员广播 receiver_id=0) OR (本人发出的记录，记于 extra.sender_admin_id).
     *
     * @param  int         $adminId      current admin-api admin id/当前 admin-api 管理员自增 id
     * @param  int         $page         page number/页码
     * @param  int         $pageSize     page size (capped to 100)/每页条数（上限 100）
     * @param  string|null $receiverType optional receiver_type filter/可选接收类型过滤
     */
    public function index(int $adminId, int $page, int $pageSize, ?string $receiverType = null): LengthAwarePaginator
    {
        // Clamp page size to the hard cap (≤100)/page_size 钳制到上限 100
        $pageSize = max(1, min($pageSize, 100));

        return Message::query()
            ->where(function (Builder $q) use ($adminId) {
                // Targeted to this admin/定向发给本人
                $q->where(function (Builder $w) use ($adminId) {
                    $w->where('receiver_type', MessageReceiverType::ADMIN->value)
                        ->where('receiver_id', $adminId);
                })
                    // Broadcast to all admins/全体管理员广播
                    ->orWhere('receiver_type', MessageReceiverType::ALL_ADMIN->value)
                    // Messages sent by me (sender tracked in extra JSON)/本人发出的记录
                    ->orWhere('extra->sender_admin_id', $adminId);
            })
            ->when(
                $receiverType !== null && $receiverType !== '',
                fn (Builder $q) => $q->where('receiver_type', $receiverType)
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    /**
     * Send a message: persist one row then broadcast the event/
     * 发送消息：持久化一行记录后触发广播事件.
     *
     * Wrapped in a DB transaction so persistence and broadcast dispatch are atomic:
     * insert the message row, capture its snow_id, then dispatch MessageSent/
     * 包在 DB 事务内：先 insert 消息行，再 dispatch 广播事件，二者保持一致.
     *
     * @param  int               $adminId     sender admin id/发送人管理员自增 id
     * @param  array             $data         validated payload/已校验入参
     * @return Message the persisted message/已持久化的消息
     */
    public function send(int $adminId, array $data): Message
    {
        // Normalize enums from validated strings/由已校验字符串还原 BackedEnum
        $receiverType = MessageReceiverType::from($data['receiver_type']);
        // Validated payload key is msg_type (matches messages.msg_type column)/已校验入参键为 msg_type
        $messageType  = MessageType::from($data['msg_type']);

        // receiver_id only meaningful for user/admin; broadcasts store 0 per contract/
        // receiver_id 仅 user/admin 有意义；广播类按契约存 0
        $receiverId = in_array($receiverType, [MessageReceiverType::USER, MessageReceiverType::ADMIN], true)
            ? (int) $data['receiver_id']
            : 0;

        // Extra carries the sender trace for admin-side listing/扩展参数记录发送人，供管理端列表识别
        $extra = ['sender_admin_id' => $adminId];

        return DB::transaction(function () use ($receiverType, $receiverId, $messageType, $data, $extra) {
            // Persist the message row/写入 messages 表
            $message = Message::create([
                'receiver_type' => $receiverType,
                'receiver_id'   => $receiverId,
                'msg_type'      => $messageType,
                'title'         => $data['title'],
                'content'       => $data['content'],
                'extra'         => $extra,
                // New message starts unread/popup off/新消息默认未读、不弹窗
                'is_read'       => false,
                'is_pop'        => false,
            ]);

            // Broadcast after commit boundary (queued by ShouldBroadcast)/事务内 dispatch，由队列异步推送
            event(new MessageSent($message));

            return $message;
        });
    }
}
