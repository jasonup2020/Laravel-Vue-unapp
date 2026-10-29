<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

/**
 * Guard isolation feature tests/守卫隔离功能测试
 *
 * Verifies the two JWT guards (api / admin-api) cannot cross-access each
 * other's protected routes, and that the issued tokens carry the expected
 * `guard` custom claim.
 *
 * 验证两套 JWT 守卫（api / admin-api）不能跨守卫访问对方受保护路由，
 * 且签发的令牌携带预期 `guard` 自定义声明。
 */
describe('GuardIsolationTest guard isolation/守卫隔离', function () {
    beforeEach(function () {
        // Seed permissions/roles/admins/menus so admin login exists/播种权限/角色/管理员/菜单使管理员登录可用
        $this->seed(DatabaseSeeder::class);
    });

    it('issues C-end tokens with guard=api claim', function () {
        $ctx = $this->loginUser();
        $token = $ctx['response']->json('data.token');

        // Parse the raw token to inspect its claims/解析原始令牌检查其声明
        $payload = auth('api')->setToken($token)->getPayload();

        expect($payload->get('guard'))->toBe('api');
    });

    it('issues admin tokens with guard=admin-api claim', function () {
        $headers = $this->loginAdmin();
        $token = str_replace('Bearer ', '', $headers['Authorization']);

        $payload = auth('admin-api')->setToken($token)->getPayload();

        expect($payload->get('guard'))->toBe('admin-api');
    });

    it('blocks a C-end token from admin routes', function () {
        $ctx = $this->loginUser();

        // C-end bearer hitting an admin-only endpoint -> code 1002/
        // C 端令牌访问管理员专属路由 -> code 1002
        $this->withHeaders($ctx['headers'])
            ->getJson('/api/admin/me')
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 1002]);
    });

    it('blocks an admin token from C-end private routes', function () {
        $headers = $this->loginAdmin();

        // Admin bearer hitting a C-end protected endpoint -> code 1002/
        // 管理员令牌访问 C 端受保护路由 -> code 1002
        $this->withHeaders($headers)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 1002]);
    });

    it('blocks an arbitrary forged token on the C-end guard', function () {
        // A garbage token string is invalid -> code 1002/伪造令牌字符串无效 -> code 1002
        $this->withHeaders(['Authorization' => 'Bearer not.a.real.token'])
            ->getJson('/api/me')
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 1002]);
    });
});
