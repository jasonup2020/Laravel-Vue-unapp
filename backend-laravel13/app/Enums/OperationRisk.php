<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Admin operation risk level/管理员操作风险等级
 */
enum OperationRisk: int
{
    case NORMAL = 0;
    case HIGH   = 1;

    /** Lang key for this risk level/返回该风险等级的多语言 key */
    public function label(): string
    {
        return match ($this) {
            self::NORMAL => 'operation.risk.normal',
            self::HIGH   => 'operation.risk.high',
        };
    }
}
