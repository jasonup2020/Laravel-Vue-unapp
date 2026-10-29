<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;

if (!function_exists('message')) {
    /**
     * Unified API response helper/统一 API 响应助手函数
     *
     * Returns shape: {success, code, msg, data}
     * - success = (code === 0)
     * - HTTP status is always 200; real result is carried by body `code`/
     *   HTTP 状态恒为 200，真实结果由 body `code` 表达
     * - msg is translated via __(); when the key is missing, Laravel returns the key itself,
     *   so both a lang key (e.g. 'api.success') and plain text are accepted.
     *
     * @param string $msg   Lang key (e.g. 'api.success') or plain text/多语言 key（如 'api.success'）或直接文本
     * @param int    $code  Business code from StatusCode; 0 means success/业务码（见 StatusCode），0 表示成功
     * @param mixed  $data  Extra payload/附加数据
     */
    function message(string $msg = 'api.success', int $code = 0, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => $code === 0,
            'code'    => $code,
            'msg'     => __($msg),
            'data'    => $data,
        ]);
    }
}
