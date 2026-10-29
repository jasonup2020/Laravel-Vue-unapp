<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Base\BaseMiddleware;
use Closure;
use App\Services\Admin\OperationLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin operation audit log middleware/管理员操作审计日志中间件
 *
 * Records every /api/admin request: admin id, url, method, client IP, request
 * params (password fields redacted to ***), response body, cost in ms, and a
 * high-risk flag (1 for password changes / role changes / kick operations).
 * Request params and response body are each truncated to 2000 chars. Logging
 * failures are swallowed and logged as warnings so business flow is never
 * blocked by audit issues.
 * Extends BaseMiddleware; the high-risk flag is the first variadic arg.
 * 记录全部 /api/admin 请求：管理员 id、url、method、客户端 IP、
 * 请求参数（密码字段脱敏为 ***）、响应体、耗时 ms、高危标记（改密/改角色/踢用户=1）。
 * 请求参数与响应体各自截断 2000 字符。日志失败吞掉并打 warning，不阻断业务。
 * 继承 BaseMiddleware；高危标记通过首个可变参数传入。
 *
 * Middleware alias/中间件别名: admin.operation.log:{isHighRisk=0|1}
 */
class AdminOperationLog extends BaseMiddleware
{
    /**
     * Handle an incoming request/处理请求
     *
     * @param  mixed ...$args  variadic middleware params; first entry is the high-risk flag ('1'=high-risk)
     *                         可变参数；首个为高危标记（'1'=高危）
     */
    public function handle(Request $request, Closure $next, mixed ...$args): Response
    {
        // High-risk flag: only literal '1' maps to 1, everything else to 0
        // 高危标记：仅字串 '1' 映射为 1，其余一律为 0
        $isHighRisk = (string) ($args[0] ?? '0');

        // Start timer before passing control to the pipeline/在进入业务前开始计时
        $start = microtime(true);
        $response = $next($request);

        try {
            // Only /api/admin requests are audited/仅审计 /api/admin 下的请求
            if (! str_starts_with($request->path(), 'api/admin')) {
                return $response;
            }

            // Cost in milliseconds/计算耗时毫秒
            $costMs = (int) round((microtime(true) - $start) * 1000);

            // Redact password-like fields then JSON encode/密码类字段脱敏后 JSON 编码
            $params = $this->sanitizeParams($request->all());
            $paramsJson = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $paramsJson = $paramsJson === false ? '' : (string) $paramsJson;

            $responseBody = (string) $response->getContent();

            // Delegate persistence to OperationLogService; truncate long bodies to 2000 chars
            // 委托 OperationLogService 落库；超长 body 截断 2000 字符
            app(OperationLogService::class)->record([
                'admin_id'        => auth('admin-api')->id(),
                'url'             => '/' . $request->path(),
                'method'          => $request->method(),
                'ip'              => client_ip(),
                'request_params'  => mb_substr($paramsJson, 0, 2000),
                'response_result' => mb_substr($responseBody, 0, 2000),
                'cost_ms'         => $costMs,
                'is_high_risk'    => $isHighRisk === '1' ? 1 : 0,
            ]);
        } catch (\Throwable $e) {
            // Audit failure must not break the business response/日志失败不阻断业务
            Log::warning('admin_operation_log_failed', [
                'err'  => $e->getMessage(),
                'path' => $request->path(),
            ]);
        }

        return $response;
    }

    /**
     * Recursively redact password-like fields/递归脱敏密码类字段
     *
     * Keys whose lowercased name contains "password" / "passwd" or equals "pwd"
     * have their string values replaced with "***". Nested arrays are walked.
     * 键名小写后含 "password" / "passwd" 或等于 "pwd" 的字符串值替换为 "***"。
     * 嵌套数组递归处理。
     */
    private function sanitizeParams(array $params): array
    {
        foreach ($params as $k => $v) {
            $key = is_string($k) ? strtolower($k) : '';

            // Password-like field: mask to ***/密码类字段：脱敏为 ***
            if (
                is_string($v)
                && (str_contains($key, 'password') || str_contains($key, 'passwd') || $key === 'pwd')
            ) {
                $params[$k] = '***';
                continue;
            }

            // Nested array: recurse/嵌套数组：递归
            if (is_array($v)) {
                $params[$k] = $this->sanitizeParams($v);
            }
        }

        return $params;
    }
}
