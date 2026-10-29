<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * In-app message type/站内消息类型
 */
enum MessageType: string
{
    case ANNOUNCE = 'announce';
    case BIZ      = 'biz';
    case WARN      = 'warn';

    /** Lang key for this message type/返回该消息类型的多语言 key */
    public function label(): string
    {
        return match ($this) {
            self::ANNOUNCE => 'message.type.announce',
            self::BIZ      => 'message.type.biz',
            self::WARN     => 'message.type.warn',
        };
    }
}
