<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Base\BaseMiddleware;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response field desensitization middleware/响应字段脱敏中间件
 *
 * Recursively masks configured sensitive fields (mobile, id_card, ...) inside
 * the JSON response body. Admins/export endpoints may opt out by sending the
 * `Skip-Desensitize: 1` header. The database always stores the raw plaintext.
 * Extends BaseMiddleware.
 * 递归脱敏 JSON 响应体内的敏感字段（mobile、id_card 等）。
 * 管理员/导出接口可通过 `Skip-Desensitize: 1` 头跳过脱敏。数据库始终存储明文。
 * 继承 BaseMiddleware。
 *
 * Middleware alias/中间件别名: response.desensitize
 */
class ResponseDesensitize extends BaseMiddleware
{
    /**
     * Handle an incoming request/处理请求
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Opt-out header skips desensitization entirely/跳过脱敏头直接放行
        if ($request->header('Skip-Desensitize') === '1') {
            return $next($request);
        }

        $response = $next($request);

        // Only JsonResponse bodies are processed; binary/streaming responses pass through
        // 仅处理 JsonResponse；二进制/流式响应原样返回
        if (! $response instanceof JsonResponse) {
            return $response;
        }

        // Decode JSON body, walk recursively, then re-encode back into the response
        // 解码 JSON body → 递归脱敏 → 重新写回响应
        $content = (string) $response->getContent();
        $data = json_decode($content, true);
        if (! is_array($data)) {
            return $response;
        }

        // Fields to mask come from config; default ['mobile','id_card']
        // 待脱敏字段来自配置，默认 ['mobile','id_card']
        $fields = (array) config('app.desensitize_fields', ['mobile', 'id_card']);
        $this->desensitize($data, $fields);

        $response->setContent(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return $response;
    }

    /**
     * Recursively mask configured fields (by reference)/递归脱敏命名字段（按引用修改）
     *
     * @param  mixed $data    decoded JSON structure/解码后的 JSON 结构
     * @param  array $fields  field names to mask/需要脱敏的字段名列表
     */
    private function desensitize(mixed &$data, array $fields): void
    {
        if (! is_array($data)) {
            return;
        }

        foreach ($data as $k => &$v) {
            // Matched string field: apply the per-field mask/命名字段为字符串：按字段类型脱敏
            if (is_string($v) && in_array($k, $fields, true)) {
                $data[$k] = $this->maskField($k, $v);
                continue;
            }

            // Nested array: recurse/嵌套数组：递归处理
            if (is_array($v)) {
                $this->desensitize($v, $fields);
            }
        }
        unset($v);
    }

    /**
     * Pick the masking strategy per field/按字段选择脱敏策略
     */
    private function maskField(string $field, string $value): string
    {
        return match ($field) {
            // mobile handled separately with length guard/手机号单独处理（含长度判断）
            'mobile'  => $this->maskMobile($value),
            // id_card keeps first 6 and last 4 chars/身份证保留前 6 后 4
            'id_card' => mask_text($value, 6, 4),
            // Default: keep first 3 and last 4/默认保留前 3 后 4
            default   => mask_text($value, 3, 4),
        };
    }

    /**
     * Mask mobile number/手机号脱敏
     *
     * Keep first 3 and last 4 chars; short values (<=7 chars) are returned as-is
     * to avoid masking test fixtures or malformed input.
     * 保留前 3 后 4；长度 ≤7 的短值原样返回，避免误伤测试数据或异常输入。
     */
    private function maskMobile(string $value): string
    {
        if (mb_strlen($value) <= 7) {
            return $value;
        }

        return mask_text($value, 3, 4);
    }
}
