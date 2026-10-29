<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Toggle admin status request/启用禁用管理员请求
 */
class AdminStatusRequest extends FormRequest
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
            // Status 1=active 0=disabled/状态 1=启用 0=禁用
            'status' => ['required', 'integer', 'in:0,1'],
        ];
    }
}
