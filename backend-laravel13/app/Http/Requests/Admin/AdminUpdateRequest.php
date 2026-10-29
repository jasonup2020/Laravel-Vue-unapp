<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update admin request/更新管理员请求
 *
 * Validates partial update payload for an existing admin
 * 校验更新已有管理员的部分字段入参
 */
class AdminUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized/是否授权
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
            // Display nickname/昵称
            'nickname' => ['sometimes', 'string', 'max:64'],
            // Email address/邮箱
            'email' => ['sometimes', 'email', 'max:128'],
            // Avatar URL/头像地址
            'avatar' => ['sometimes', 'nullable', 'string', 'max:255'],
            // Account status/账号状态
            'status' => ['sometimes', 'integer', 'in:0,1'],
            // Role snow_id list to sync/同步的角色 snow_id 列表
            'role_ids' => ['sometimes', 'array'],
            // Each role snow_id must exist/每个角色 snow_id 必须存在
            'role_ids.*' => ['integer', 'exists:roles,snow_id'],
        ];
    }
}
