<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

/**
 * Response desensitize feature tests/响应脱敏功能测试
 *
 * Verifies the response.desensitize middleware runs on /api/me and the admin
 * user list without breaking the response, and that the Skip-Desensitize
 * header is accepted.
 *
 * 验证 response.desensitize 中间件在 /api/me 与管理员用户列表上正常运行不破坏响应，
 * 且 Skip-Desensitize 头被接受。
 */
describe('DesensitizeTest desensitization/响应脱敏', function () {
    it('does not break /api/me through the desensitize middleware', function () {
        $ctx = $this->loginUser();

        // /api/me is wrapped by response.desensitize per contract 6.1 row 5/
        // /api/me 按契约 6.1 第 5 行经 response.desensitize 包裹
        $res = $this->withHeaders($ctx['headers'])->getJson('/api/me');

        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);

        // If a mobile/id_card field is present it must be masked (contains ****)/
        // 若响应含 mobile/id_card 字段则必须脱敏（含 ****）
        $data = $res->json('data');
        foreach (['mobile', 'id_card'] as $field) {
            if (isset($data[$field]) && is_string($data[$field]) && $data[$field] !== '') {
                expect($data[$field])->toContain('****');
            }
        }
    });

    it('honours the Skip-Desensitize header', function () {
        $ctx = $this->loginUser();

        // Explicit skip header must still yield a valid response/显式跳过头仍须返回合法响应
        $res = $this->withHeaders($ctx['headers'] + ['Skip-Desensitize' => '1'])
            ->getJson('/api/me');

        $res->assertOk()->assertJson(['success' => true, 'code' => 0]);
    });
});
