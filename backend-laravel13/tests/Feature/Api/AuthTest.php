<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * C-end authentication feature tests/C 端认证功能测试
 *
 * Covers login success/failure, disabled account, token refresh, logout
 * blacklisting and the protected /api/me closure. All responses return HTTP
 * 200; the business code lives in the JSON body (0=success, 1002=unauthenticated).
 *
 * 覆盖登录成功/失败、禁用账号、令牌刷新、登出拉黑与受保护 /api/me 闭环。
 * 全部响应 HTTP 200，业务码位于 JSON 体（0=成功，1002=未认证）。
 */
describe('AuthTest C-end auth/C 端认证', function () {
    /**
     * Helper: perform a C-end login request/发起 C 端登录请求
     *
     * Fetches a fresh one-time captcha first (login requires captcha_id/code)/
     * 先取新的一次性验证码（登录必填 captcha_id/code）
     */
    function doLogin(TestCase $t, string $email, string $password): TestResponse
    {
        // Fetch a fresh one-time captcha/取新的一次性验证码
        $cap = $t->getJson('/api/captcha');
        $capId = (string) $cap->json('data.captcha_id');
        // Test cache is array driver, read plaintext back/测试缓存为 array 驱动，回读明文
        $capCode = (string) \Illuminate\Support\Facades\Cache::get("captcha:{$capId}");

        return $t->postJson('/api/login', [
            'email' => $email,
            'password' => $password,
            'captcha_id' => $capId,
            'captcha_code' => $capCode,
        ]);
    }

    it('logs in and returns the token envelope', function () {
        // Arrange a user with a known password/准备已知密码的用户
        $user = User::factory()->create(['password' => 'password']);

        // Act/发起登录
        $res = doLogin($this, $user->email, 'password');

        // Success envelope: success=true, body code=0/成功信封：success=true，体 code=0
        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // data.token must be a non-empty JWT string; expires_in numeric/data.token 为非空 JWT 字符串，expires_in 为数值
        expect($res->json('data.token'))->toBeString()->not->toBeEmpty();
        expect($res->json('data.expires_in'))->toBeNumeric();
    });

    it('rejects a wrong password with business code 1002', function () {
        $user = User::factory()->create(['password' => 'password']);

        $res = doLogin($this, $user->email, 'wrong-password');

        // Invalid credentials -> HTTP 200 + body code 1002/账号或密码错误 -> HTTP 200 + 体 code 1002
        $res->assertOk()->assertJson(['success' => false, 'code' => 1002]);
    });

    it('rejects a disabled user', function () {
        // status=0 means disabled per UserStatus/status=0 表示禁用
        $user = User::factory()->create(['password' => 'password', 'status' => 0]);

        $res = doLogin($this, $user->email, 'password');

        // Disabled account is rejected (HTTP 200, success=false)/禁用账号被拒（HTTP 200，success=false）
        $res->assertOk();
        expect($res->json('success'))->toBeFalse();
    });

    it('refreshes the token and blacklists the old one', function () {
        $ctx = $this->loginUser();
        $oldToken = $this->bearer($ctx['response']->json('data.token'))['Authorization'];

        // Call refresh with the current token/携带当前令牌调用刷新
        $res = $this->withHeaders($ctx['headers'])->postJson('/api/refresh');

        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);
        $newToken = $res->json('data.token');
        expect($newToken)->toBeString()->not->toBeEmpty()->not->toBe($oldToken);

        // The old token must now be blacklisted -> code 1002/旧令牌应已进黑名单 -> code 1002
        $this->withHeaders(['Authorization' => $oldToken])
            ->getJson('/api/me')
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 1002]);
    });

    it('logs out and rejects the blacklisted token', function () {
        $ctx = $this->loginUser();

        // Logout with the issued token/携带签发令牌登出
        $this->withHeaders($ctx['headers'])
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJson(['success' => true, 'code' => 0]);

        // Accessing a protected route after logout -> code 1002/登出后访问受保护路由 -> code 1002
        $this->withHeaders($ctx['headers'])
            ->getJson('/api/me')
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 1002]);
    });

    it('returns the current profile without exposing internal id or password', function () {
        $ctx = $this->loginUser();

        $res = $this->withHeaders($ctx['headers'])->getJson('/api/me');

        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // snow_id is the external identifier (15-16 digit number)/snow_id 为对外标识（15-16 位数字）
        $snowId = $res->json('data.snow_id');
        expect($snowId)->toBeNumeric()->toBeGreaterThan(100000000000000);

        // Auto-increment id and password must never leak/自增 id 与密码绝不能外泄
        expect($res->json('data'))->not->toHaveKey('id');
        expect($res->json('data'))->not->toHaveKey('password');
    });

    it('rejects unauthenticated access to /api/me', function () {
        // No Authorization header at all/完全不带认证头 -> code 1002
        $this->getJson('/api/me')
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 1002]);
    });

    it('rejects unauthenticated access with browser Accept header (no JSON)', function () {
        // Simulate a raw browser hit: Accept: text/html, no Authorization./
        // 模拟浏览器直接访问：Accept: text/html，无 Authorization。
        // Must still return unified JSON (HTTP 200 + code 1002), NOT a redirect
        // to a non-existent "login" route/302/5000。
        $res = $this->withHeaders(['Accept' => 'text/html'])
            ->get('/api/me');

        $res->assertOk();
        $res->assertJson(['success' => false, 'code' => 1002]);
    });
});
