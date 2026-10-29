<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin create product request/管理端创建商品请求
 *
 * Note: products table has no stock column; description column is `desc`
 * 注意：products 表无 stock 列，描述列名为 desc
 */
class ProductStoreRequest extends FormRequest
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
            // Product name/商品名称
            'name'   => ['required', 'string', 'max:255'],
            // Price >= 0.01/价格最小 0.01
            'price'  => ['required', 'numeric', 'min:0.01'],
            // Status aligned with ProductStatus enum (1=on, 0=off)/状态对齐枚举（1上架 0下架）
            'status' => ['required', Rule::enum(ProductStatus::class)],
            // Optional description/可选商品描述
            'desc'   => ['nullable', 'string'],
        ];
    }
}
