<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin send in-app message request validation/管理员发送站内消息请求校验.
 */
class AdminMessageSendRequest extends FormRequest
{
    /**
     * Authorize (route already guarded by admin-api + permission middleware)/
     * 授权通过（路由已由 admin-api + 权限中间件保护）.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules/校验规则.
     *
     * receiver_type ∈ user/admin/all_user/all_admin; receiver_id required only for
     * user/admin; title/content required; msg_type ∈ announce/biz/warn/
     * receiver_type 四选一；receiver_id 仅 user/admin 必填；title/content 必填；msg_type 三选一.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Target audience type/接收方类型
            'receiver_type' => ['required', 'string', 'in:user,admin,all_user,all_admin'],
            // Required when targeting a single user or admin/定向单用户或管理员时必填
            'receiver_id'   => ['nullable', 'required_if:receiver_type,user', 'required_if:receiver_type,admin', 'integer'],
            // Message title/消息标题
            'title'         => ['required', 'string', 'max:200'],
            // Message body/消息内容
            'content'       => ['required', 'string'],
            // Message category (matches messages.msg_type column)/消息类别（对应 messages.msg_type 列）
            'msg_type'      => ['required', 'string', 'in:announce,biz,warn'],
        ];
    }

    /**
     * Validation messages (i18n keys)/校验失败提示（多语言 key）.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receiver_type.required' => __('api.message.receiver_type_invalid'),
            'receiver_type.in'       => __('api.message.receiver_type_invalid'),
            'receiver_id.required_if' => __('api.param_error'),
            'receiver_id.integer'    => __('api.param_error'),
            'title.required'         => __('api.validation_error'),
            'title.max'              => __('api.validation_error'),
            'content.required'       => __('api.validation_error'),
            'msg_type.required'      => __('api.message.receiver_type_invalid'),
            'msg_type.in'            => __('api.message.receiver_type_invalid'),
        ];
    }
}
