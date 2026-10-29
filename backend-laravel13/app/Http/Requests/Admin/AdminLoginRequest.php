<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin login request validation/管理员登录请求校验.
 */
class AdminLoginRequest extends FormRequest
{
    /**
     * Authorize (login endpoint is public)/授权通过（登录接口公开）.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules: required username; required password min 6; required captcha/
     * 校验规则：用户名必填；密码必填至少 6 位；验证码必填.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6'],
            'captcha_id' => ['required', 'string'],
            'captcha_code' => ['required', 'string'],
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
            'username.required' => __('api.validation_error'),
            'password.required' => __('api.validation_error'),
            'password.min' => __('api.validation_error'),
            'captcha_id.required' => __('api.captcha.required'),
            'captcha_code.required' => __('api.captcha.required'),
        ];
    }
}
