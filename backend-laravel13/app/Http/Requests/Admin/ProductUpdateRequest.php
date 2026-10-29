<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin update product request/管理端更新商品请求
 *
 * All fields optional (sometimes)/全部字段可选
 */
class ProductUpdateRequest extends FormRequest
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
            'name'   => ['sometimes', 'required', 'string', 'max:255'],
            'price'  => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'status' => ['sometimes', 'required', Rule::enum(ProductStatus::class)],
            'desc'   => ['nullable', 'string'],
        ];
    }
}
