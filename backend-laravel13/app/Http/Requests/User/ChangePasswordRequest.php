<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Change password request validation/修改密码请求校验.
 */
class ChangePasswordRequest extends FormRequest
{
    /**
     * Authorize/授权通过.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules: old password required; new password required min 6 and confirmed/
     * 校验规则：原密码必填；新密码必填至少 6 位且与 password_confirmation 一致.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'old_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ];
    }

    /**
     * Validation messages (i18n keys)/校验失败提示（多语言 key）.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'old_password.required' => __('api.validation_error'),
            'password.required' => __('api.validation_error'),
            'password.min' => __('api.validation_error'),
            'password.confirmed' => __('api.validation_error'),
        ];
    }
}
