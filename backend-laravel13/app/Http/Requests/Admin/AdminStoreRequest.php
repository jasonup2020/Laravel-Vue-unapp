<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Create admin request/创建管理员请求
 *
 * Validates admin creation payload including role bindings
 * 校验创建管理员入参（含角色绑定）
 */
class AdminStoreRequest extends FormRequest
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
            // Login username, unique on admins table/登录账号，admins 表唯一
            'username' => ['required', 'string', 'max:32', 'unique:admins,username'],
            // Display nickname/昵称
            'nickname' => ['nullable', 'string', 'max:64'],
            // Email address/邮箱
            'email' => ['nullable', 'email', 'max:128'],
            // Avatar URL/头像地址
            'avatar' => ['nullable', 'string', 'max:255'],
            // Initial password min 6 chars/初始密码，最少 6 位
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            // Account status 1=active 0=disabled/账号状态 1=启用 0=禁用
            'status' => ['nullable', 'integer', 'in:0,1'],
            // Role snow_id list to bind/绑定的角色 snow_id 列表
            'role_ids' => ['nullable', 'array'],
            // Each role snow_id must exist/每个角色 snow_id 必须存在
            'role_ids.*' => ['integer', 'exists:roles,snow_id'],
        ];
    }
}
