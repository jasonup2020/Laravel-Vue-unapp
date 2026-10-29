<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Dict item status/字典项状态
 */
enum DictStatus: int
{
    case ENABLED  = 1;
    case DISABLED = 0;

    /** Lang key for this status label/返回该状态的多语言 key */
    public function label(): string
    {
        return match ($this) {
            self::ENABLED  => 'dict.status.enabled',
            self::DISABLED => 'dict.status.disabled',
        };
    }
}
