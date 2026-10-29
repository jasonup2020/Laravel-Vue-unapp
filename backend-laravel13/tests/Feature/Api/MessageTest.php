<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Message;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

/**
 * In-app messaging feature tests/站内消息功能测试
 *
 * Verifies an admin-sent message reaches the target C-end user, unread
 * counting and mark-as-read flow, plus the all_user broadcast record.
 *
 * 验证管理员发送的消息送达目标 C 端用户、未读统计与标记已读闭环，
 * 以及 all_user 广播记录。
 */
describe('MessageTest in-app messaging/站内消息', function () {
    beforeEach(function () {
        $this->seed(DatabaseSeeder::class);
    });

    it('delivers an admin message to the target user and tracks unread count', function () {
        // loginUser() creates the recipient C-end user AND logs them in/
        // loginUser() 同时创建收件 C 端用户并完成登录，避免 email 唯一冲突
        $ctx = $this->loginUser();
        $user = $ctx['user'];
        $admin = $this->loginAdmin();

        // Admin sends a direct message to the user/管理员向该用户发送定向消息
        // NOTE: receiver_id uses the user internal id (C-end filters by auth()->id())
        // 注意：receiver_id 传用户内部 id（C 端按 auth()->id() 过滤）
        $send = $this->withHeaders($admin)->postJson('/api/admin/messages', [
            'receiver_type' => 'user',
            'receiver_id' => $user->id,
            'msg_type' => 'biz',
            'title' => 'Welcome aboard',
            'content' => 'Hello from admin',
            'is_pop' => 0,
        ]);
        $send->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // C-end user sees the message in their list/C 端用户在列表中看到该消息
        $list = $this->withHeaders($ctx['headers'])->getJson('/api/messages');
        $list->assertOk()->assertJson(['success' => true, 'code' => 0]);

        expect($list->json('data.list'))->toBeArray()->not->toBeEmpty();
        expect($list->json('data.list.0.title'))->toBe('Welcome aboard');

        // Unread count is 1 after delivery/送达后未读数为 1
        $unread = $this->withHeaders($ctx['headers'])->getJson('/api/messages/unread-count');
        $unread->assertOk()->assertJson(['success' => true, 'code' => 0]);
        // Tolerate data=1 / data={count:1} / data={unread_count:1}/兼容 data 为数值或 data={count:n} 或 data={unread_count:n}
        $readUnread = fn () => is_numeric($unread->json('data'))
            ? $unread->json('data')
            : ($unread->json('data.unread_count') ?? $unread->json('data.count'));
        expect((int) $readUnread())->toBe(1);

        // Mark the message read by its snow_id/按 snow_id 标记该消息已读
        $msgSnowId = $list->json('data.list.0.snow_id');
        $this->withHeaders($ctx['headers'])
            ->putJson("/api/messages/read/{$msgSnowId}")
            ->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // Unread count drops to 0/未读数降为 0
        $after = $this->withHeaders($ctx['headers'])->getJson('/api/messages/unread-count');
        $readAfter = fn () => is_numeric($after->json('data'))
            ? $after->json('data')
            : ($after->json('data.unread_count') ?? $after->json('data.count'));
        expect((int) $readAfter())->toBe(0);
    });

    it('records an all_user broadcast message', function () {
        $admin = $this->loginAdmin();

        $res = $this->withHeaders($admin)->postJson('/api/admin/messages', [
            'receiver_type' => 'all_user',
            'receiver_id' => null,
            'msg_type' => 'announce',
            'title' => 'System notice',
            'content' => 'All users read this',
            'is_pop' => 1,
        ]);

        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // A broadcast row persists with receiver_type=all_user/广播消息落库且 receiver_type=all_user
        expect(Message::where('receiver_type', 'all_user')->count())->toBe(1);
    });
});
