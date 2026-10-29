<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Notices list request/Notices 列表请求校验
 */
class NoticesIndexRequest extends FormRequest
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
            'page'      => 'nullable|integer|min:1',
            'page_size' => 'nullable|integer|min:1|max:100',
            'status'    => 'nullable|integer|in:0,1',
        ];
    }
}