<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Unified response business code/统一响应业务码
 *
 * Convention/约定：
 * - HTTP status is always 200; real result is carried by `code` in response body/
 *   HTTP 状态恒为 200，真实结果由响应体 code 表达
 * - 0 = success; non-zero = business error/0 表示成功，非 0 表示业务错误
 * - 2xxxx reserved for per-module custom business codes/2xxxx 预留给各模块自定义业务码
 */
enum StatusCode: int
{
    case SUCCESS          = 0;
    case UNKNOWN_ERROR    = 1;
    case PARAM_ERROR      = 1001;
    case UNAUTHORIZED     = 1002;
    case FORBIDDEN        = 1003;
    case NOT_FOUND        = 1004;
    case VALIDATION       = 1005;
    case RATE_LIMITED     = 1006;
    case CONFLICT         = 1007;
    case CAPTCHA          = 1008;
    case INTERNAL         = 5000;

    /** Default lang key for this code/该码对应的默认多语言 key */
    public function label(): string
    {
        return match ($this) {
            self::SUCCESS      => 'api.success',
            self::UNKNOWN_ERROR => 'api.common.biz_error',
            self::PARAM_ERROR  => 'api.param_error',
            self::UNAUTHORIZED => 'api.unauthorized',
            self::FORBIDDEN    => 'api.forbidden',
            self::NOT_FOUND    => 'api.not_found',
            self::VALIDATION   => 'api.validation_error',
            self::RATE_LIMITED => 'api.rate_limited',
            self::CONFLICT     => 'api.conflict',
            self::CAPTCHA      => 'api.captcha.invalid',
            self::INTERNAL     => 'api.server_error',
        };
    }
}
