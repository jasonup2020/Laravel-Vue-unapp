<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Rate-limit rule update request/限流规则更新请求
 *
 * Same validation as StoreRequest but the unique rule on rule_key ignores
 * the row currently being edited (by auto-increment id from route binding).
 * 校验规则与新增请求一致，但 rule_key 唯一性校验忽略当前编辑行
 * （按路由绑定的自增 id 排除自身）。
 */
class RateLimitRuleUpdateRequest extends FormRequest
{
    /**
     * Authorize/权限校验
     *
     * Route-level RBAC middleware (admin.permission:admin:rate:update) already
     * guards this endpoint; allow here.
     * 路由级 RBAC 中间件（admin.permission:admin:rate:update）已鉴权，此处放行。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules/校验规则
     *
     * The unique rule on rule_key ignores the current row's id so that
     * editing a row without changing its rule_key does not trip the unique
     * constraint.
     * rule_key 唯一性校验排除当前行 id，避免编辑未改 rule_key 时误判冲突。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Current row id from route binding {id}/路由绑定 {id} 的当前行 id
        $id = (int) $this->route('id');

        return [
            // Rule key; unique except for the row being updated/规则标识，唯一性排除当前行
            'rule_key'      => ['sometimes', 'required', 'string', 'max:100', 'unique:rate_limit_rules,rule_key,' . $id],
            // Max attempts within the decay window, 1..100000/时间窗口内最大尝试次数，1..100000
            'max_attempts'  => ['sometimes', 'required', 'integer', 'min:1', 'max:100000'],
            // Decay window length in seconds, 1..86400/时间窗口秒数，1..86400
            'decay_seconds' => ['sometimes', 'required', 'integer', 'min:1', 'max:86400'],
            // Optional enabled flag/可选启用标记
            'enabled'       => ['sometimes', 'boolean'],
            // Optional remark, max 255 chars; may be set to null/可选备注，最长 255 字符；可显式置空
            'remark'        => ['nullable', 'string', 'max:255'],
        ];
    }
}
