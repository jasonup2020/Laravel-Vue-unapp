<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Reverb WebSocket channel authorization routes/Reverb WebSocket 频道鉴权路由
|--------------------------------------------------------------------------
| These closures answer the pusher `auth` handshake that laravel-eocket/echo
| performs when a client subscribes to a private/presence channel. The public
| channel needs no closure at all (clients may subscribe anonymously).
| 这些闭包用于响应 pusher 私有频道订阅握手；公共频道无需鉴权闭包。
*/

// C-end user private channel/普通用户私有频道
// Guard restricted to `api`; only the matching user id may subscribe to their own channel/
// 守卫限定 api，仅 id 匹配的本人可订阅自己的频道
Broadcast::channel('private-user.{id}', function ($user, $id) {
    return (int) $user->getAuthIdentifier() === (int) $id;
}, ['guards' => ['api']]);

// Admin private channel/管理员私有频道
// Guard restricted to `admin-api`; only the matching admin id may subscribe/
// 守卫限定 admin-api，仅 id 匹配的本人可订阅
Broadcast::channel('private-admin.{id}', function ($admin, $id) {
    return (int) $admin->getAuthIdentifier() === (int) $id;
}, ['guards' => ['admin-api']]);

// Public system announcement channel/系统公共公告频道
// Public channel, no auth closure: any connected client may subscribe/
// 公共频道，无需鉴权闭包：任何已连接客户端均可订阅
// (Intentionally not registered via Broadcast::channel — presence/private only are authed by Laravel)
