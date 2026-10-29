<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Product on/off shelf status/商品上下架状态
 */
enum ProductStatus: int
{
    case ON  = 1;
    case OFF = 0;

    /** Lang key for this status label/返回该状态的多语言 key */
    public function label(): string
    {
        return match ($this) {
            self::ON  => 'product.status.on',
            self::OFF => 'product.status.off',
        };
    }
}
