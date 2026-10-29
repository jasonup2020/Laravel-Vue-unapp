<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\DictStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin update dict item request/管理端更新字典项请求
 *
 * All fields optional (sometimes)/全部字段可选
 */
class DictUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to this request/
     * 判断当前用户是否授权（权限由 admin.permission 中间件把关）
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
            'type'   => ['sometimes', 'required', 'string', 'max:64'],
            'label'  => ['sometimes', 'required', 'string', 'max:255'],
            'label_en' => ['sometimes', 'nullable', 'string', 'max:50'],
            'value'  => ['sometimes', 'required', 'string', 'max:255'],
            'sort'   => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::enum(DictStatus::class)],
        ];
    }
}
