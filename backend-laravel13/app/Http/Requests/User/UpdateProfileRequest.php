<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update profile request validation/更新个人资料请求校验.
 * Matches service scope: name only/与 UserService::updateProfile 支持范围一致：仅 name.
 */
class UpdateProfileRequest extends FormRequest
{
    /**
     * Authorize/授权通过.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules: required name, max 50/校验规则：name 必填、最长 50.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
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
            'name.required' => __('api.validation_error'),
            'name.max' => __('api.validation_error'),
        ];
    }
}
