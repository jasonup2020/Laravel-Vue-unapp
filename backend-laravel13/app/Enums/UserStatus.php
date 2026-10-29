<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * User (C-end) status/普通用户状态
 */
enum UserStatus: int
{
    case ACTIVE  = 1;
    case DISABLED = 0;

    /** Lang key for this status label/返回该状态的多语言 key */
    public function label(): string
    {
        return match ($this) {
            self::ACTIVE   => 'user.status.active',
            self::DISABLED => 'user.status.disabled',
        };
    }
}
