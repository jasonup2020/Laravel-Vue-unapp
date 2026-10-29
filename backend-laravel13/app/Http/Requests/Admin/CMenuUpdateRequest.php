<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Update C-end menu request/更新C端菜单请求
 *
 * Cycle prevention (parent_id = self or descendant) is enforced in
 * Admin\CMenuService::update() — this request only does basic format/
 * exists validation.
 * 防环校验（parent_id 指向自身或子孙）在 Admin\CMenuService::update()
 * 中执行——本 Request 仅做基础格式/存在性校验。
 */
class CMenuUpdateRequest extends FormRequest
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
        $snowId = (int) $this->route('id');

        return [
            // Parent menu snow_id (0 = root)/父菜单 snow_id（0 为根节点）
            'parent_id' => [
                'sometimes',
                'integer',
                'min:0',
                // Existence check only when parent_id > 0 (skip for root=0)/
                // 存在性校验仅在 parent_id > 0 时生效（根节点 0 跳过）
                function ($attribute, $value, $fail): void {
                    $val = (int) $value;
                    if ($val > 0 && ! DB::table('c_menus')->where('snow_id', $val)->whereNull('deleted_at')->exists()) {
                        $fail('api.menu.parent_not_found');
                    }
                },
            ],
            // Chinese display title/中文标题
            'title' => ['sometimes', 'string', 'max:100'],
            // English display title/英文标题
            'title_en' => ['sometimes', 'nullable', 'string', 'max:100'],
            // Icon name/图标
            'icon' => ['sometimes', 'nullable', 'string', 'max:64'],
            // Frontend route path (unique ignoring self)/前端路由 path（唯一性忽略自身）
            'path' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('c_menus', 'path')->ignore($snowId, 'snow_id'),
            ],
            // External URL/外部链接地址
            'url' => ['sometimes', 'nullable', 'string', 'max:500'],
            // Sort order/排序
            'sort' => ['sometimes', 'integer', 'min:0'],
            // Whether visible on C-end/C端是否显示
            'is_show' => ['sometimes', 'boolean'],
        ];
    }
}
