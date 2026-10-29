<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * Admin single sign-on (SSO)/管理员单点登录测试
 *
 * Each admin keeps exactly one active session. A newer login replaces the
 * stored online token, so the older token is rejected by check.admin.online.
 * 每个管理员只允许一个有效会话：新登录覆盖在线 token，旧 token 被
 * check.admin.online 中间件拒绝（被挤下线）。
 */
uses(RefreshDatabase::class);

it('enforces single sign-on: a newer admin login kicks the older token', function () {
    $admin = Admin::factory()->create(['password' => 'admin123']);

    // First login -> token1; second login -> token2 replaces the online token
    // 第一次登录拿 token1；第二次登录 token2 覆盖当前在线 token
    $h1 = $this->loginAdmin($admin->username, 'admin123');
    $h2 = $this->loginAdmin($admin->username, 'admin123');

    // Older token is kicked -> rejected/旧 token 被挤下线 -> 拒绝
    $r1 = $this->withHeaders($h1)->getJson('/api/admin/me');
    expect($r1->json('success'))->toBe(false);

    // Newest token remains valid/最新 token 仍有效
    $r2 = $this->withHeaders($h2)->getJson('/api/admin/me');
    expect($r2->json('success'))->toBe(true);
    expect($r2->json('code'))->toBe(0);
});
