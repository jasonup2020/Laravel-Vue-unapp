<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Legacy semantic error codes (HTTP-style values)/旧版语义错误码（HTTP 风格值）
 *
 * @deprecated Internal semantic marker only; the response body `code` MUST use
 *             {@see StatusCode}. Handler maps via {@see self::toStatusCode()}.
 *             仅作内部语义标记，对外响应体 code 必须使用 {@see StatusCode}；由 handler
 *             通过 {@see self::toStatusCode()} 映射输出。
 */
enum ErrorCode: int
{
    case UNAUTHORIZED       = 401;
    case FORBIDDEN          = 403;
    case NOT_FOUND           = 404;
    case CONFLICT            = 409;
    case UNPROCESSABLE      = 422;
    case TOO_MANY_REQUESTS   = 429;
    case BUSINESS            = 400;
    case CAPTCHA_INVALID     = 460;
    case INTERNAL            = 500;

    /**
     * Map this legacy semantic code to the unified StatusCode used in response body/
     * 将内部语义码映射为对外统一 StatusCode
     *
     * 400→1001, 401→1002, 403→1003, 404→1004, 422→1005,
     * 429→1006, 409→1007, 460→1008, 500→5000.
     */
    public function toStatusCode(): StatusCode
    {
        return match ($this) {
            self::UNAUTHORIZED      => StatusCode::UNAUTHORIZED,
            self::FORBIDDEN         => StatusCode::FORBIDDEN,
            self::NOT_FOUND         => StatusCode::NOT_FOUND,
            self::CONFLICT          => StatusCode::CONFLICT,
            self::UNPROCESSABLE     => StatusCode::VALIDATION,
            self::TOO_MANY_REQUESTS => StatusCode::RATE_LIMITED,
            self::BUSINESS          => StatusCode::PARAM_ERROR,
            self::CAPTCHA_INVALID   => StatusCode::CAPTCHA,
            self::INTERNAL          => StatusCode::INTERNAL,
        };
    }

    /** Lang key for this error code/返回该错误码的多语言 key */
    public function label(): string
    {
        return $this->toStatusCode()->label();
    }
}
