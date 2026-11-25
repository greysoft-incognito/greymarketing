<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    /**
     * Statuses that still give access to the plan until `ends_at`.
     *
     * @return array<int, string>
     */
    public static function current(): array
    {
        return [self::ACTIVE->value, self::CANCELLED->value];
    }
}
