<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update permission request/更新权限请求
 *
 * Code uniqueness ignores the current record; type may be changed
 * code 唯一性忽略自身；type 可修改
 */
class PermissionUpdateRequest extends FormRequest
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
        // Current permission snow_id from route/路由中的权限 snow_id
        $snowId = (int) $this->route('id');

        return [
            // Permission display name/权限名称
            'name' => ['sometimes', 'string', 'max:50'],
            // English display name (fallback to name)/英文显示名（缺省回退 name）
            'label_en' => ['sometimes', 'nullable', 'string', 'max:50'],
            // Unique code ignoring self by snow_id/code/唯一性按 snow_id 忽略自身
            'code' => [
                'sometimes',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9:_-]+$/',
                Rule::unique('permissions', 'code')->ignore($snowId, 'snow_id'),
            ],
            // Resource group name/资源分组名
            'group' => ['sometimes', 'string', 'max:50'],
            // Action name/动作名
            'action' => ['sometimes', 'string', 'max:50'],
            // Type 1=system 2=custom/类型 1=系统 2=自定义
            'type' => ['sometimes', 'integer', 'in:1,2'],
            // Remark/备注
            'remark' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
