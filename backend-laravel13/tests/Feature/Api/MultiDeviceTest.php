<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

/**
 * Multi-device login control feature tests/多端登录管控功能测试
 *
 * Verifies the C-end cap of 3 concurrent devices: a 4th login evicts the
 * oldest token, and the admin kick endpoint invalidates every token of a user.
 *
 * 验证 C 端最多 3 端同时在线：第 4 次登录剔除最早令牌，
 * 且管理员踢下线接口使用户全部令牌失效。
 */
describe('MultiDeviceTest multi-device/多端登录', function () {
    beforeEach(function () {
        $this->seed(DatabaseSeeder::class);
    });

    it('evicts the oldest token after the 4th login', function () {
        $user = User::factory()->create(['password' => 'password']);

        // Login 4 times from distinct "devices"/从不同"设备"登录 4 次
        // Use a dedicated fingerprint so the rate-limit counter stays isolated/
        // 使用独立指纹使限流计数与其他用例隔离
        $tokens = [];
        for ($i = 0; $i < 4; $i++) {
            // Fresh one-time captcha per login attempt/每次登录取新的一次性验证码
            $captcha = $this->captchaPair();
            $res = $this->withHeaders(['X-Client-Fingerprint' => 'fp-multi-device'])
                ->postJson('/api/login', [
                    'email' => $user->email,
                    'password' => 'password',
                    'captcha_id' => $captcha['captcha_id'],
                    'captcha_code' => $captcha['captcha_code'],
                ]);
            $tokens[] = $res->json('data.token');
        }

        expect($tokens[0])->not->toBe($tokens[3]);

        // The evicted oldest token must be rejected on a protected route -> code 1002/
        // 被剔除的最早令牌访问受保护路由必须被拒 -> code 1002
        $this->withHeaders(['Authorization' => 'Bearer '.$tokens[0]])
            ->getJson('/api/me')
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 1002]);

        // A still-valid token keeps working/仍有效令牌可正常访问
        $this->withHeaders(['Authorization' => 'Bearer '.$tokens[3]])
            ->getJson('/api/me')
            ->assertOk()->assertJson(['success' => true, 'code' => 0]);
    });

    it('reports the online device count for an admin', function () {
        $user = User::factory()->create(['password' => 'password']);

        // 3 concurrent devices are allowed/允许 3 端同时在线
        for ($i = 0; $i < 3; $i++) {
            $captcha = $this->captchaPair();
            $this->withHeaders(['X-Client-Fingerprint' => 'fp-multi-device-online'])
                ->postJson('/api/login', [
                    'email' => $user->email,
                    'password' => 'password',
                    'captcha_id' => $captcha['captcha_id'],
                    'captcha_code' => $captcha['captcha_code'],
                ]);
        }

        $admin = $this->loginAdmin();
        $res = $this->withHeaders($admin)
            ->getJson("/api/admin/users/{$user->snow_id}/online");

        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);
        // Online count is 3; tolerate data=3 or data={online:3}/{count:3}/
        // 在线数为 3；兼容 data 直接为数值或 data={online:3}/{count:3}
        $raw = $res->json('data');
        $count = is_numeric($raw) ? (int) $raw : (int) ($raw['online'] ?? $raw['count'] ?? 0);
        expect($count)->toBe(3);
    });

    it('kicks all devices of a user via the admin endpoint', function () {
        $user = User::factory()->create(['password' => 'password']);

        $tokens = [];
        for ($i = 0; $i < 3; $i++) {
            $captcha = $this->captchaPair();
            $res = $this->withHeaders(['X-Client-Fingerprint' => 'fp-multi-device-kick'])
                ->postJson('/api/login', [
                    'email' => $user->email,
                    'password' => 'password',
                    'captcha_id' => $captcha['captcha_id'],
                    'captcha_code' => $captcha['captcha_code'],
                ]);
            $tokens[] = $res->json('data.token');
        }

        // Admin kicks every device of the user/管理员踢掉该用户全部终端
        $admin = $this->loginAdmin();
        $this->withHeaders($admin)
            ->postJson("/api/admin/users/{$user->snow_id}/kick")
            ->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // Every previously valid token is now rejected -> code 1002/此前有效令牌全部被拒 -> code 1002
        foreach ($tokens as $token) {
            $this->withHeaders(['Authorization' => 'Bearer '.$token])
                ->getJson('/api/me')
                ->assertOk()
                ->assertJson(['success' => false, 'code' => 1002]);
        }
    });
});
