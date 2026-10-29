<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminMessageSendRequest;
use App\Services\Admin\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin-side in-app message controller/管理端站内消息控制器.
 *
 * Routes mounted by S16 under auth:admin-api + permission + operation log/
 * 路由由 S16 挂载，守卫 auth:admin-api + 权限 + 操作日志.
 */
class MessageController extends Controller
{
    public function __construct(private MessageService $messages)
    {
    }

    /**
     * Paginate admin-facing messages/管理员消息分页.
     * Optional ?receiver_type= filter/可选 ?receiver_type= 过滤.
     */
    public function index(Request $request): JsonResponse
    {
        $page     = (int) $request->query('page', 1);
        $pageSize = (int) $request->query('page_size', 15);
        // Optional receiver_type filter/可选接收类型过滤
        $receiverType = $request->query('receiver_type');
        $receiverType = is_string($receiverType) ? $receiverType : null;

        $paginator = $this->messages->index(
            (int) $request->user()->getAuthIdentifier(),
            $page,
            $pageSize,
            $receiverType
        );

        return message('api.success', 0, [
            'list'      => collect($paginator->items())->map(fn ($m) => $this->format($m))->all(),
            'total'     => $paginator->total(),
            'page'      => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ]);
    }

    /**
     * Send a message (targeted or broadcast)/发送消息（定向或广播）.
     * Persists the row and triggers the MessageSent broadcast event/
     * 持久化记录并触发 MessageSent 广播事件；写操作由路由挂载操作日志（高危标记）.
     */
    public function send(AdminMessageSendRequest $request): JsonResponse
    {
        $message = $this->messages->send(
            (int) $request->user()->getAuthIdentifier(),
            $request->validated()
        );

        return message('api.message.sent', 0, [
            'snow_id' => $message->snow_id,
        ]);
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
            'snow_id'       => $m->snow_id,
            'receiver_type' => $m->receiver_type?->value ?? $m->receiver_type,
            'receiver_id'   => in_array($m->receiver_type?->value, ['user', 'admin'], true) ? (int) $m->receiver_id : null,
            'title'         => $m->title,
            'content'       => $m->content,
            'message_type'  => $m->msg_type?->value ?? $m->msg_type,
            'is_read'       => (bool) $m->is_read,
            'is_pop'        => (bool) $m->is_pop,
            'created_at'    => optional($m->created_at)->toIso8601String(),
        ];
    }
}
