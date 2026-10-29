<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Create C-end menu request/创建C端菜单请求
 */
class CMenuStoreRequest extends FormRequest
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
            // Parent menu snow_id, 0 = root/父菜单 snow_id，0 为根节点
            'parent_id' => [
                'nullable',
                'integer',
                'min:0',
                // Existence check only when parent_id > 0 (skip for root=0)/
                // 存在性校验仅在 parent_id > 0 时生效（根节点 0 跳过）
                function ($attribute, $value, $fail): void {
                    $val = (int) $value;
                    if ($val > 0 && ! \DB::table('c_menus')->where('snow_id', $val)->whereNull('deleted_at')->exists()) {
                        $fail('api.menu.parent_not_found');
                    }
                },
            ],
            // Chinese display title/中文标题
            'title' => ['required', 'string', 'max:100'],
            // English display title/英文标题
            'title_en' => ['nullable', 'string', 'max:100'],
            // Icon name/图标
            'icon' => ['nullable', 'string', 'max:64'],
            // Frontend route path/前端路由 path
            'path' => ['required', 'string', 'max:255', 'unique:c_menus,path'],
            // External URL/外部链接地址
            'url' => ['nullable', 'string', 'max:500'],
            // Sort order, smaller first/排序，数字越小越靠前
            'sort' => ['nullable', 'integer', 'min:0'],
            // Whether visible on C-end/C端是否显示
            'is_show' => ['nullable', 'boolean'],
        ];
    }
}
