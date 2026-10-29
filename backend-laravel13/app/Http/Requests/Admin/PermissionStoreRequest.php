<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Create permission request/创建权限请求
 *
 * Validates action-level permission payload including code format and type
 * 校验动作级权限入参，含 code 格式与 type
 */
class PermissionStoreRequest extends FormRequest
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
            // Permission display name/权限名称
            'name' => ['required', 'string', 'max:50'],
            // English display name (fallback to name)/英文显示名（缺省回退 name）
            'label_en' => ['nullable', 'string', 'max:50'],
            // Unique permission code e.g. admin:product:export/权限唯一标识，如 admin:product:export
            'code' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9:_-]+$/',
                'unique:permissions,code',
            ],
            // Resource group name/资源分组名
            'group' => ['required', 'string', 'max:50'],
            // Action name e.g. list/create/update/delete/export/动作名，如 list/create/update/delete/export
            'action' => ['required', 'string', 'max:50'],
            // Type 1=system preset 2=custom/类型 1=系统预置 2=自定义
            'type' => ['required', 'integer', 'in:1,2'],
            // Remark/备注
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
