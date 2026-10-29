<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update admin menu request/更新后台菜单请求
 */
class MenuUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized/是否授权
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
            // Parent menu snow_id/父菜单 snow_id
            'parent_id' => ['sometimes', 'integer', 'min:0'],
            // Menu display title/菜单标题
            'title' => ['sometimes', 'string', 'max:100'],
            // English menu title/菜单英文标题
            'title_en' => ['sometimes', 'nullable', 'string', 'max:100'],
            // Frontend route path/前端路由 path
            'path' => ['sometimes', 'string', 'max:255'],
            // Frontend component path/前端组件路径
            'component' => ['sometimes', 'nullable', 'string', 'max:255'],
            // Icon name/图标
            'icon' => ['sometimes', 'nullable', 'string', 'max:64'],
            // Bound permission code/绑定权限码
            'permission_code' => ['sometimes', 'nullable', 'string', 'max:128'],
            // Sort order/排序
            'sort' => ['sometimes', 'integer', 'min:0'],
            // Whether show in sidebar/是否侧边栏显示
            'is_show' => ['sometimes', 'boolean'],
            // Whether keep-alive cache/是否 keep-alive 缓存
            'is_cache' => ['sometimes', 'boolean'],
            // Whether external link/是否外部链接
            'is_external' => ['sometimes', 'boolean'],
            // External URL/外部链接地址
            'external_url' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
