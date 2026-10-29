<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Admin (B-end) status/管理员状态
 */
enum AdminStatus: int
{
    case ACTIVE   = 1;
    case DISABLED = 0;

    /** Lang key for this status label/返回该状态的多语言 key */
    public function label(): string
    {
        return match ($this) {
            self::ACTIVE   => 'admin.status.active',
            self::DISABLED => 'admin.status.disabled',
        };
    }
}
