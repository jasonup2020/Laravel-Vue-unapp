<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin Notices store request/管理端 Notices 新增请求校验
 */
class NoticesStoreRequest extends FormRequest
{
    /**
     * Authorize/权限校验
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules/校验规则
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'name'   => 'required|string|max:100',
            'title'  => 'nullable|string|max:200',
            'status' => 'required|integer|in:0,1',
            'remark' => 'nullable|string|max:500',
        ];
    }
}