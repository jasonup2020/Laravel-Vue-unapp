<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create role request/创建角色请求
 */
class RoleStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:64'],
            // Unique role code among non-deleted roles e.g. super-admin
            // 角色唯一标识（仅校验未软删记录，软删后允许复用 code）
            'code' => ['required', 'string', 'max:64', Rule::unique('roles', 'code')->whereNull('deleted_at')],
            // Remark/备注
            'remark' => ['nullable', 'string', 'max:500'],
        ];
    }
}
