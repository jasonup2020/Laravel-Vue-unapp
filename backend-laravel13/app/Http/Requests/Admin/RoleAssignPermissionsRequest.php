<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Assign permissions to role request/角色分配权限请求
 */
class RoleAssignPermissionsRequest extends FormRequest
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
            // Permission snow_id list to sync/同步的权限 snow_id 列表
            'permission_ids' => ['required', 'array'],
            // Each permission snow_id must exist/每个权限 snow_id 必须存在
            'permission_ids.*' => ['integer', 'exists:permissions,snow_id'],
        ];
    }
}
