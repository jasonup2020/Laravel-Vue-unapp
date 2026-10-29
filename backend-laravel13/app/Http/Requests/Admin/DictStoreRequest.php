<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\DictStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin create dict item request/管理端创建字典项请求
 */
class DictStoreRequest extends FormRequest
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
            // Dict type key/字典类型标识
            'type'   => ['required', 'string', 'max:64'],
            // Display label/显示文本
            'label'  => ['required', 'string', 'max:255'],
            // English display label/英文显示文本
            'label_en' => ['nullable', 'string', 'max:50'],
            // Stored value/存储值
            'value'  => ['required', 'string', 'max:255'],
            // Sort order, default 0/排序序号，默认 0
            'sort'   => ['nullable', 'integer', 'min:0'],
            // Status aligned with DictStatus enum (1=enabled, 0=disabled)/状态对齐枚举（1启用 0禁用）
            'status' => ['nullable', Rule::enum(DictStatus::class)],
        ];
    }
}
