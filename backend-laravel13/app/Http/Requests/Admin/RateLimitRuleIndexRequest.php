<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Rate-limit rule list request/限流规则列表请求
 *
 * @package App\Http\Requests\Admin
 */
class RateLimitRuleIndexRequest extends FormRequest
{
    /**
     * Authorize/权限校验
     *
     * Route-level RBAC middleware (admin.permission:admin:rate:list) already
     * guards this endpoint; allow here.
     * 路由级 RBAC 中间件（admin.permission:admin:rate:list）已鉴权，此处放行。
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
            // Page number, defaults to 1/页码，默认 1
            'page'      => ['nullable', 'integer', 'min:1'],
            // Page size, clamped to <=100/每页条数，上限 100
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
            // Optional fuzzy filter on rule_key/可选 rule_key 模糊过滤
            'rule_key'  => ['nullable', 'string', 'max:100'],
            // Optional enabled filter: 1=on, 0=off/可选启用状态过滤：1=启用，0=停用
            'enabled'   => ['nullable', 'boolean'],
        ];
    }
}
