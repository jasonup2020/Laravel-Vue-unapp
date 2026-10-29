<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update role request/更新角色请求
 *
 * Code is immutable after creation/角色 code 创建后不可修改
 */
class RoleUpdateRequest extends FormRequest
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
            // Role display name/角色名称
            'name' => ['sometimes', 'string', 'max:64'],
            // Remark/备注
            'remark' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
