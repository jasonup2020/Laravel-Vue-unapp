<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reset admin password request/重置管理员密码请求
 *
 * Used by admin to reset another admin's password (no old password needed)
 * 管理员重置其他管理员密码（无需原密码）
 */
class ResetPasswordRequest extends FormRequest
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
            // New password min 6, confirmed by password_confirmation/新密码最少 6 位，需 password_confirmation 确认
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ];
    }
}
