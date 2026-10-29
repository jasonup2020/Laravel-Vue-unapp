<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin favorite list request/管理端收藏列表请求
 *
 * Full-isolation extension: admin-side only, no C-end reuse.
 * Filters favorites by user outward snow_id (optional).
 * 全隔离扩展示例：仅管理端使用，不与 C 端复用；按用户对外 snow_id 可选过滤。
 */
class FavoriteIndexRequest extends FormRequest
{
    /**
     * Authorize/权限校验
     *
     * Route permission (admin.permission:admin:favorite:list) is enforced by middleware;
     * here we only validate query input
     * 路由权限由 admin.permission 中间件把关，此处仅校验查询入参
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
            // Page number/页码
            'page'      => ['nullable', 'integer', 'min:1'],
            // Page size clamped to <=100/每页条数上限 100
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
            // user_id carries the outward snow_id of users table (optional filter)/
            // user_id 对外语义为 users.snow_id（可选过滤）
            'user_id'   => ['nullable', 'integer'],
        ];
    }
}
