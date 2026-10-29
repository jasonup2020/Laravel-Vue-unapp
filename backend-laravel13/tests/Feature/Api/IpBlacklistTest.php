<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\IpBlacklist;
use Tests\TestCase;

/**
 * IP blacklist feature tests/IP 黑名单功能测试
 *
 * Verifies the IpBlacklist middleware intercepts a listed IP with 403 while
 * allowing normal traffic through.
 *
 * 验证 IpBlacklist 中间件对黑名单 IP 返回 403，同时放行正常流量。
 */
describe('IpBlacklistTest IP blacklist/IP 黑名单', function () {
    it('blocks a blacklisted IP with 403', function () {
        // Persist a permanent block for a test client IP/为测试客户端 IP 持久化永久封禁
        IpBlacklist::create([
            'ip' => '203.0.113.7',
            'remark' => 'probe block',
            'expire_at' => null,
        ]);

        // Spoof the client IP via X-Forwarded-For (proxies trusted)/通过 X-Forwarded-For 伪造客户端 IP（信任代理）
        $res = $this->withHeaders(['X-Forwarded-For' => '203.0.113.7'])
            ->getJson('/api/health');

        // Blocked IP -> HTTP 200 + body code 1003 (forbidden)/黑名单 IP -> HTTP 200 + 体 code 1003（无权限）
        $res->assertOk()->assertJson(['success' => false, 'code' => 1003]);
    });

    it('allows an unlisted IP', function () {
        // No blacklist row for this IP/该 IP 无封禁记录
        $res = $this->withHeaders(['X-Forwarded-For' => '198.51.100.9'])
            ->getJson('/api/health');

        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);
    });

    it('does not block an expired blacklist entry', function () {
        // Expired block must be treated as released/过期封禁视为放行
        IpBlacklist::create([
            'ip' => '203.0.113.20',
            'remark' => 'expired probe',
            'expire_at' => now()->subHour(),
        ]);

        $res = $this->withHeaders(['X-Forwarded-For' => '203.0.113.20'])
            ->getJson('/api/health');

        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);
    });
});
