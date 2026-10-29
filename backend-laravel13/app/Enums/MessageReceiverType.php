<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * In-app message receiver type/站内消息接收方类型
 */
enum MessageReceiverType: string
{
    case USER     = 'user';
    case ADMIN    = 'admin';
    case ALL_USER = 'all_user';
    case ALL_ADMIN = 'all_admin';

    /** Lang key for this receiver type/返回该接收方类型的多语言 key */
    public function label(): string
    {
        return match ($this) {
            self::USER     => 'message.receiver.user',
            self::ADMIN    => 'message.receiver.admin',
            self::ALL_USER => 'message.receiver.all_user',
            self::ALL_ADMIN => 'message.receiver.all_admin',
        };
    }
}
