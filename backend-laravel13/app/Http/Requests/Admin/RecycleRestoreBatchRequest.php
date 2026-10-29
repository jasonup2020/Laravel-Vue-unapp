<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate batch-restore input/校验批量恢复入参
 *
 * Expects a non-empty `ids` array of outward-facing snow_ids.
 * 接收非空 `ids` 数组，元素为对外 snow_id。
 */
class RecycleRestoreBatchRequest extends FormRequest
{
    /**
     * Authorization check/权限校验
     *
     * Route-level RBAC middleware (admin.permission:admin:recycle:restore) already
     * guards this endpoint; allow here.
     * 路由级 RBAC 中间件（admin.permission:admin:recycle:restore）已鉴权，此处放行。
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
            // Required, at least one id/必填，至少一个 id
            'ids'   => ['required', 'array', 'min:1'],
            // Each id must be an integer snow_id/每个 id 必须为整数 snow_id
            'ids.*' => ['integer'],
        ];
    }
}
