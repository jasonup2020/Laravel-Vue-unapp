<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Tests\TestCase;

/**
 * Captcha feature tests/验证码功能测试.
 *
 * Covers: GET /api/captcha envelope, missing captcha (1005), wrong captcha (1008),
 * correct captcha login (0) and one-time consumption (reuse → 1008).
 * 覆盖：验证码信封、缺失(1005)、错误(1008)、正确登录(0) 与一次性复用(1008).
 */
describe('CaptchaTest/验证码', function () {

    it('GET /api/captcha returns code 0 with id and png data url', function () {
        // Act/发起请求
        $res = $this->getJson('/api/captcha');

        // Success envelope/成功信封
        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // captcha_id non-empty; image is a PNG data URL/id 非空；image 为 PNG data URL
        expect($res->json('data.captcha_id'))->toBeString()->not->toBeEmpty();
        expect($res->json('data.image'))->toStartWith('data:image/png;base64,');
    });

    it('rejects login missing captcha fields with 1005', function () {
        // Arrange a real user/准备真实用户
        $user = User::factory()->create(['password' => 'password']);

        // Login WITHOUT captcha fields → validation 1005/登录不带验证码字段 → 校验 1005
        $res = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $res->assertOk()->assertJsonPath('code', 1005);
    });

    it('rejects wrong captcha with 1008', function () {
        $user = User::factory()->create(['password' => 'password']);

        // Unknown captcha id → treated as expired/未知 id → 视为过期
        $res = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'captcha_id' => 'nonexistent-id',
            'captcha_code' => 'ZZZZ',
        ]);

        $res->assertOk()->assertJsonPath('code', 1008);
    });

    it('logs in with correct captcha and consumes it (reuse -> 1008)', function () {
        $user = User::factory()->create(['password' => 'password']);

        // Fetch a real captcha pair (id + plaintext code from array cache)/取真实验证码对
        $cap = $this->captchaPair();

        // First login succeeds/首次登录成功
        $first = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'captcha_id' => $cap['captcha_id'],
            'captcha_code' => $cap['captcha_code'],
        ]);
        $first->assertOk()->assertJson(['success' => true, 'code' => 0]);
        expect($first->json('data.token'))->toBeString()->not->toBeEmpty();

        // Reuse the same captcha id → already consumed → 1008/同 id 复用 → 已消费 → 1008
        $second = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'captcha_id' => $cap['captcha_id'],
            'captcha_code' => $cap['captcha_code'],
        ]);
        $second->assertOk()->assertJsonPath('code', 1008);
    });
});
