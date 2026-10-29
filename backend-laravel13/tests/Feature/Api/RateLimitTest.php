<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Tests\TestCase;

/**
 * Composite rate limit feature tests/复合限流功能测试
 *
 * Exercises the CompositeRateLimit middleware: exceeding per-route attempts
 * yields 429, and the counter key varies by route path and client fingerprint.
 *
 * 验证 CompositeRateLimit 中间件：按路由超限返回 429，且计数 key 随路由路径
 * 与浏览器指纹变化。
 */
describe('RateLimitTest composite rate limit/复合限流', function () {
    // Seed the rate-limit rules table; otherwise the middleware falls back to
    // a permissive default and never throttles/
    // 播种限流规则表；否则中间件兜底为宽松默认值，永不触发限流。
    beforeEach(function () {
        $this->seed(\Database\Seeders\RateLimitRuleSeeder::class);
    });

    it('returns 429 after exceeding the login attempt quota', function () {
        // Login route is limited to 5 attempts/60s per contract 6.2/登录路由按契约 6.2 限流 5 次/60 秒
        $user = User::factory()->create(['password' => 'password']);

        $last = null;
        for ($i = 0; $i < 6; $i++) {
            // Fresh one-time captcha per attempt (rate limiter runs before captcha,
            // so the 6th is throttled regardless)/
            // 每次尝试取新验证码（限流中间件在验证码校验前，第 6 次无论验证码都被限）
            $captcha = $this->captchaPair();
            $last = $this->withHeaders(['X-Client-Fingerprint' => 'fp-rate-001'])
                ->postJson('/api/login', [
                    'email' => $user->email,
                    'password' => 'password',
                    'captcha_id' => $captcha['captcha_id'],
                    'captcha_code' => $captcha['captcha_code'],
                ]);
        }

        // The 6th request must be rate limited -> body code 1006/第 6 次请求必须被限流 -> 体 code 1006
        $last->assertOk()
            ->assertJson(['success' => false, 'code' => 1006]);
    });

    it('uses a separate counter for a different fingerprint', function () {
        $user = User::factory()->create(['password' => 'password']);

        // Burn the quota for fingerprint A/耗尽指纹 A 的配额
        for ($i = 0; $i < 5; $i++) {
            $captcha = $this->captchaPair();
            $this->withHeaders(['X-Client-Fingerprint' => 'fp-rate-A'])
                ->postJson('/api/login', [
                    'email' => $user->email,
                    'password' => 'password',
                    'captcha_id' => $captcha['captcha_id'],
                    'captcha_code' => $captcha['captcha_code'],
                ]);
        }

        // Fingerprint B gets its own fresh counter/指纹 B 拥有独立全新计数器
        $captchaB = $this->captchaPair();
        $res = $this->withHeaders(['X-Client-Fingerprint' => 'fp-rate-B'])
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'password',
                'captcha_id' => $captchaB['captcha_id'],
                'captcha_code' => $captchaB['captcha_code'],
            ]);

        // Not rate limited -> a fresh fingerprint logs in successfully/
        // 未被限流 -> 全新指纹登录成功
        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);
    });

    it('does not bleed the login limit into other routes', function () {
        $user = User::factory()->create(['password' => 'password']);

        // Burn the login route quota/耗尽登录路由配额
        for ($i = 0; $i < 6; $i++) {
            $captcha = $this->captchaPair();
            $this->withHeaders(['X-Client-Fingerprint' => 'fp-rate-C'])
                ->postJson('/api/login', [
                    'email' => $user->email,
                    'password' => 'password',
                    'captcha_id' => $captcha['captcha_id'],
                    'captcha_code' => $captcha['captcha_code'],
                ]);
        }

        // A different route path has its own counter -> 200 code 0/不同路由路径有独立计数器 -> 200 code 0
        $this->withHeaders(['X-Client-Fingerprint' => 'fp-rate-C'])
            ->getJson('/api/products')
            ->assertOk()->assertJson(['success' => true, 'code' => 0]);
    });
});
