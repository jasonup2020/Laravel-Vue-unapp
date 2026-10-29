<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Create admin menu request/创建后台菜单请求
 */
class MenuStoreRequest extends FormRequest
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
            // Parent menu internal snow_id, 0 = root/父菜单 snow_id，0 为根节点
            'parent_id' => ['nullable', 'integer', 'min:0'],
            // Menu display title/菜单标题
            'title' => ['required', 'string', 'max:100'],
            // English menu title/菜单英文标题
            'title_en' => ['nullable', 'string', 'max:100'],
            // Frontend route path/前端路由 path
            'path' => ['required', 'string', 'max:255'],
            // Frontend component path/前端组件路径
            'component' => ['nullable', 'string', 'max:255'],
            // Icon name/图标
            'icon' => ['nullable', 'string', 'max:64'],
            // Bound permission code e.g. admin:user:list/绑定权限码
            'permission_code' => ['nullable', 'string', 'max:128'],
            // Sort order, smaller first/排序，数字越小越靠前
            'sort' => ['nullable', 'integer', 'min:0'],
            // Whether show in sidebar/是否侧边栏显示
            'is_show' => ['nullable', 'boolean'],
            // Whether keep-alive cache/是否 keep-alive 缓存
            'is_cache' => ['nullable', 'boolean'],
            // Whether external link/是否外部链接
            'is_external' => ['nullable', 'boolean'],
            // External URL/外部链接地址
            'external_url' => ['nullable', 'string', 'max:500'],
        ];
    }
}
