<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Assign menus to role request/角色分配菜单请求
 */
class RoleAssignMenusRequest extends FormRequest
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
            // Menu snow_id list to sync/同步的菜单 snow_id 列表
            'menu_ids' => ['required', 'array'],
            // Each menu snow_id must exist/每个菜单 snow_id 必须存在
            'menu_ids.*' => ['integer', 'exists:admin_menus,snow_id'],
        ];
    }
}
