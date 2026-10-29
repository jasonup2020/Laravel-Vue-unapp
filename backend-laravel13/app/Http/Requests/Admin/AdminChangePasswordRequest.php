<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin self change password request/管理员修改自身密码请求
 *
 * Requires old password verification before setting new password
 * 修改自身密码需校验原密码
 */
class AdminChangePasswordRequest extends FormRequest
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
            // Current password for verification/原密码校验
            'old_password' => ['required', 'string'],
            // New password min 6, confirmed by password_confirmation/新密码最少 6 位，需确认
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ];
    }
}
