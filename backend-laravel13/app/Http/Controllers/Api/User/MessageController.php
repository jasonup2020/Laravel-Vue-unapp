<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Services\User\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * C-end in-app message controller/C 端站内消息控制器.
 *
 * Routes mounted by S16 under auth:api + check.user.online/
 * 路由由 S16 挂载，守卫 auth:api + check.user.online.
 */
class MessageController extends Controller
{
    public function __construct(private MessageService $messages)
    {
    }

    /**
     * Paginate my messages/我的消息分页.
     * Optional ?is_read=0|1 filter; sorted by created_at desc/
     * 可选 ?is_read=0|1 过滤，按 created_at 倒序.
     */
    public function index(Request $request): JsonResponse
    {
        // Resolve optional is_read filter: absent => null (all), else cast bool/
        // 解析可选已读过滤：未传参 => null（全部），传参则转布尔
        $rawIsRead = $request->query('is_read');
        $isRead = $rawIsRead === null ? null : (bool) $rawIsRead;

        $page     = (int) $request->query('page', 1);
        $pageSize = (int) $request->query('page_size', 15);

        // Pass the authenticated user id into the service (no auth() read inside)/
        // 身份 id 由控制器传入，Service 内不读 auth()
        $paginator = $this->messages->index((int) $request->user()->getAuthIdentifier(), $page, $pageSize, $isRead);

        return message('api.success', 0, [
            'list'      => collect($paginator->items())->map(fn ($m) => $this->format($m))->all(),
            'total'     => $paginator->total(),
            'page'      => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ]);
    }

    /**
     * Unread message count for me/我的未读消息数.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->messages->unreadCount((int) $request->user()->getAuthIdentifier());

        return message('api.success', 0, ['unread_count' => $count]);
    }

    /**
     * Mark a single message as read (id = snow_id)/标记单条消息已读（id 为 snow_id）.
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $this->messages->read((int) $request->user()->getAuthIdentifier(), $id);

        return message('api.message.marked_read');
    }

    /**
     * Mark all my visible messages as read/将本人全部可见消息标记已读.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $affected = $this->messages->readAll((int) $request->user()->getAuthIdentifier());

        return message('api.message.all_read', 0, ['affected' => $affected]);
    }

    /**
     * Normalize a message model to outward array (snow_id only)/
     * 规范化消息模型为对外数组（仅 snow_id）.
     *
     * @param  \App\Models\Message $m message model/消息模型
     * @return array<string, mixed>
     */
    private function format($m): array
    {
        return [
            'snow_id'      => $m->snow_id,
            'receiver_type' => $m->receiver_type?->value ?? $m->receiver_type,
            'title'        => $m->title,
            'content'      => $m->content,
            'message_type'  => $m->msg_type?->value ?? $m->msg_type,
            'is_read'      => (bool) $m->is_read,
            'is_pop'       => (bool) $m->is_pop,
            'created_at'   => optional($m->created_at)->toIso8601String(),
        ];
    }
}
