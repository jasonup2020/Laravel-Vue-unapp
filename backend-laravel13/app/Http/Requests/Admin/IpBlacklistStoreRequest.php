<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate create-blacklist input/校验新增封禁入参
 *
 * IP format is validated by Laravel's `ip` rule (FILTER_VALIDATE_IP). The
 * active-duplicate check (permanent or unexpired rows) lives in
 * IpBlacklistService so that expired rows may be re-banned.
 * IP 格式由 Laravel `ip` 规则校验（FILTER_VALIDATE_IP）。
 * 生效中重复校验（永久或未过期行）在 IpBlacklistService 内完成，
 * 以允许对已过期行重新封禁。
 */
class IpBlacklistStoreRequest extends FormRequest
{
    /**
     * Authorization check/权限校验
     *
     * Route-level RBAC middleware (admin.permission:admin:ip:create) already
     * guards this endpoint; allow here.
     * 路由级 RBAC 中间件（admin.permission:admin:ip:create）已鉴权，此处放行。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules/校验规则
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Required, valid IPv4/IPv6 address/必填，合法 IPv4/IPv6 地址
            'ip'        => ['required', 'string', 'max:45', 'ip'],
            // Optional remark, max 255 chars/可选备注，最长 255 字符
            'remark'    => ['nullable', 'string', 'max:255'],
            // Optional expiry datetime; null means permanent/可选过期时间；null 表示永久封禁
            'expire_at' => ['nullable', 'date'],
        ];
    }
}
