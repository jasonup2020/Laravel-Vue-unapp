<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for PUT /api/admin/locale/{lang}/后台语言包更新请求校验
 *
 * Body/请求体：{"items": {"api.success": "新文案", ...}}
 * - items must be a non-empty array/items 必须为非空数组
 * - every value must be a string/每个值必须为字符串
 */
class LocaleUpdateRequest extends FormRequest
{
    /**
     * Authorize: let the admin.permission middleware decide/
     * 授权：交由 admin.permission 中间件判定
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules/校验规则
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'items'   => 'required|array|min:1',
            'items.*' => 'required|string',
        ];
    }

    /**
     * Human-readable error messages/错误提示
     *
     * @return array<string,string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'api.param_error',
            'items.array'    => 'api.param_error',
            'items.min'      => 'api.param_error',
            'items.*.string' => 'api.param_error',
        ];
    }
}
