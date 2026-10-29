<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Rate-limit rule store request/限流规则新增请求
 *
 * Validates the payload for creating a new rate_limit_rules row.
 * 校验创建限流规则行的入参。
 */
class RateLimitRuleStoreRequest extends FormRequest
{
    /**
     * Authorize/权限校验
     *
     * Route-level RBAC middleware (admin.permission:admin:rate:create) already
     * guards this endpoint; allow here.
     * 路由级 RBAC 中间件（admin.permission:admin:rate:create）已鉴权，此处放行。
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
            // Rule key identifier, must be unique across rate_limit_rules/规则标识，在 rate_limit_rules 表中唯一
            'rule_key'      => ['required', 'string', 'max:100', 'unique:rate_limit_rules,rule_key'],
            // Max attempts within the decay window, 1..100000/时间窗口内最大尝试次数，1..100000
            'max_attempts'  => ['required', 'integer', 'min:1', 'max:100000'],
            // Decay window length in seconds, 1..86400/时间窗口秒数，1..86400
            'decay_seconds' => ['required', 'integer', 'min:1', 'max:86400'],
            // Optional enabled flag, defaults to true when omitted/可选启用标记，缺省视为启用
            'enabled'       => ['sometimes', 'boolean'],
            // Optional remark, max 255 chars/可选备注，最长 255 字符
            'remark'        => ['nullable', 'string', 'max:255'],
        ];
    }
}
