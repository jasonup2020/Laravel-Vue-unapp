<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * C-end store favorite request/C 端收藏商品请求
 *
 * Full-isolation extension: C-end only, no admin-side reuse
 * 全隔离扩展示例：仅 C 端使用，不与管理端复用
 */
class FavoriteStoreRequest extends FormRequest
{
    /**
     * Authorize/权限校验
     *
     * Auth guard is enforced by route middleware (auth:api);
     * here we only validate input
     * 登录由路由中间件 auth:api 把关，此处仅做入参校验
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
            // product_id carries the outward snow_id of products table/
            // product_id 对外语义为 products.snow_id
            'product_id' => [
                'required',
                'integer',
                // The favoritable product must exist; off-shelf / soft-deleted handling
                // is delegated to the service layer/
                // 收藏对象须存在；下架与软删后的处理由 Service 层决策
                Rule::exists('products', 'snow_id'),
            ],
        ];
    }
}
