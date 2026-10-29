<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Enums\ErrorCode;
use App\Enums\MessageReceiverType;
use App\Exceptions\ApiException;
use App\Models\Message;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * C-end in-app message service/C 端站内消息服务.
 *
 * Handles message listing, unread counter, read marking for the current api-guard user/
 * 面向 api 守卫普通用户处理消息列表、未读统计与标记已读.
 */
class MessageService
{
    /**
     * Paginate the messages visible to the given user/分页拉取当前用户可见的消息.
     *
     * Visible scope = (receiver_type=user AND receiver_id=me) OR (receiver_type=all_user).
     * all_user rows store receiver_id=0 per contract section 3 (messages table).
     * 可见范围 = (定向本人) OR (全体用户广播，receiver_id 存 0)，按 created_at 倒序.
     *
     * @param  int       $userId   current api-guard user id/当前 api 守卫用户自增 id
     * @param  int       $page     page number/页码
     * @param  int       $pageSize page size (capped to 100)/每页条数（上限 100）
     * @param  bool|null $isRead   optional read filter; null=all, true=read, false=unread/
     *                             可选已读过滤；null=全部，true=已读，false=未读
     */
    public function index(int $userId, int $page, int $pageSize, ?bool $isRead = null): LengthAwarePaginator
    {
        // Clamp page size to the hard cap (≤100)/page_size 钳制到上限 100
        $pageSize = max(1, min($pageSize, 100));

        return Message::query()
            ->where(function (Builder $q) use ($userId) {
                // Targeted to this user/定向发给本人
                $q->where(function (Builder $w) use ($userId) {
                    $w->where('receiver_type', MessageReceiverType::USER->value)
                        ->where('receiver_id', $userId);
                })->orWhere('receiver_type', MessageReceiverType::ALL_USER->value); // Public broadcast/全体广播
            })
            ->when($isRead !== null, fn (Builder $q) => $q->where('is_read', $isRead))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    /**
     * Count unread messages for the given user/统计当前用户未读消息数.
     *
     * Direct DB count here (no cache): unread count changes on every read/broadcast,
     * caching would introduce staleness and an extra invalidation path; the
     * (receiver_type,receiver_id,is_read) index keeps the count cheap./
     * 此处直接走 DB count，不加缓存：未读数随已读/广播频繁变化，缓存会引入脏读与额外失效成本，
     * 且 (receiver_type,receiver_id,is_read) 索引可保证 count 廉价.
     */
    public function unreadCount(int $userId): int
    {
        return Message::query()
            ->where(function (Builder $q) use ($userId) {
                $q->where(function (Builder $w) use ($userId) {
                    $w->where('receiver_type', MessageReceiverType::USER->value)
                        ->where('receiver_id', $userId);
                })->orWhere('receiver_type', MessageReceiverType::ALL_USER->value);
            })
            ->where('is_read', false)
            ->count();
    }

    /**
     * Mark a single message (identified by snow_id) as read/将指定消息（按 snow_id）标记已读.
     *
     * Only the owner may read it: targeted-to-me or an all_user broadcast.
     * Throws 404 when not found; throws 403 when it exists but is not mine./
     * 仅本人可标记：定向本人或全体广播；不存在抛 404，存在但非本人抛 403.
     */
    public function read(int $userId, int $snowId): void
    {
        // Resolve by outward snow_id (auto-increment id is never exposed)/按对外 snow_id 解析（不暴露自增 id）
        $message = Message::query()->where('snow_id', $snowId)->first();

        if ($message === null) {
            // Not found/消息不存在
            throw new ApiException(ErrorCode::NOT_FOUND, 'api.message.not_found');
        }

        // Determine whether the message belongs to this user/判定消息是否归属本人
        $isMine = ($message->receiver_type === MessageReceiverType::USER
                && (int) $message->receiver_id === $userId)
            || $message->receiver_type === MessageReceiverType::ALL_USER;

        if (! $isMine) {
            // Exists but not allowed/存在但无权访问
            throw new ApiException(ErrorCode::FORBIDDEN, 'api.forbidden');
        }

        // Mark read only when still unread (avoids pointless write)/未读才更新，避免无效写
        if (! $message->is_read) {
            $message->is_read = true;
            $message->save();
        }
    }

    /**
     * Mark every visible message of the user as read/将本人全部可见消息批量标记已读.
     *
     * @return int number of rows affected/受影响行数
     */
    public function readAll(int $userId): int
    {
        return Message::query()
            ->where(function (Builder $q) use ($userId) {
                $q->where(function (Builder $w) use ($userId) {
                    $w->where('receiver_type', MessageReceiverType::USER->value)
                        ->where('receiver_id', $userId);
                })->orWhere('receiver_type', MessageReceiverType::ALL_USER->value);
            })
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }
}
