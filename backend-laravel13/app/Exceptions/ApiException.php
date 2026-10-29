<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCode;

/**
 * Business API exception/业务 API 异常
 *
 * Carries an internal semantic {@see ErrorCode}; the global ApiExceptionHandler
 * maps it to {@see StatusCode} for the response body. HTTP status is always 200/
 * 携带内部语义 {@see ErrorCode}，由全局 ApiExceptionHandler 映射为 {@see StatusCode}
 * 输出到响应体。HTTP 状态恒为 200。
 */
class ApiException extends \RuntimeException
{
    /**
     * Legacy HTTP-style status (kept for backward compat); NOT used as HTTP response status/
     * 旧版 HTTP 风格状态（向后兼容保留），不再作为 HTTP 响应状态
     */
    public int $statusCode;

    /** Business error code enum (semantic marker)/业务错误码枚举（语义标记） */
    public ErrorCode $errorCode;

    /** Extra payload (e.g. field validation errors)/附加数据（如字段校验错误明细） */
    public array $data;

    /**
     * @param ErrorCode $code       Business error code enum/业务错误码枚举
     * @param string    $msg        Lang key or plain text/多语言 key 或直接文本
     * @param array     $data       Extra payload/附加数据
     * @param int|null  $statusCode Legacy HTTP-style status, ignored at render time/
     *                              旧版 HTTP 风格状态，渲染时不再使用
     */
    public function __construct(
        ErrorCode $code,
        string $msg,
        array $data = [],
        ?int $statusCode = null
    ) {
        parent::__construct($msg, $code->value);

        $this->errorCode = $code;
        $this->data      = $data;
        $this->statusCode = $statusCode ?? self::defaultStatusCode($code);
    }

    /**
     * Derive legacy HTTP-style status from error code/按错误码推导旧版 HTTP 风格状态
     *
     * Kept for backward compat; renderer uses {@see ErrorCode::toStatusCode()} instead./
     * 仅向后兼容，渲染时改用 {@see ErrorCode::toStatusCode()}。
     */
    private static function defaultStatusCode(ErrorCode $code): int
    {
        return match ($code) {
            ErrorCode::UNAUTHORIZED      => 401,
            ErrorCode::FORBIDDEN         => 403,
            ErrorCode::NOT_FOUND         => 404,
            ErrorCode::CONFLICT          => 409,
            ErrorCode::UNPROCESSABLE     => 422,
            ErrorCode::TOO_MANY_REQUESTS => 429,
            ErrorCode::INTERNAL          => 500,
            default                      => 400, // BUSINESS
        };
    }

    public static function unauthorized(string $msg = 'api.unauthorized', array $data = []): self
    {
        return new self(ErrorCode::UNAUTHORIZED, $msg, $data);
    }

    public static function forbidden(string $msg = 'api.forbidden', array $data = []): self
    {
        return new self(ErrorCode::FORBIDDEN, $msg, $data);
    }

    public static function notFound(string $msg = 'api.not_found', array $data = []): self
    {
        return new self(ErrorCode::NOT_FOUND, $msg, $data);
    }

    public static function conflict(string $msg = 'api.conflict', array $data = []): self
    {
        return new self(ErrorCode::CONFLICT, $msg, $data);
    }

    public static function tooMany(string $msg = 'api.rate_limited', array $data = []): self
    {
        return new self(ErrorCode::TOO_MANY_REQUESTS, $msg, $data);
    }

    public static function business(string $msg = 'api.common.biz_error', array $data = []): self
    {
        return new self(ErrorCode::BUSINESS, $msg, $data);
    }

    public static function validation(string $msg = 'api.validation_error', array $data = []): self
    {
        return new self(ErrorCode::UNPROCESSABLE, $msg, $data);
    }
}
