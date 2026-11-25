<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case DRAFT = 'draft';
    case SCHEDULED = 'scheduled';
    case PREPARING = 'preparing';
    case SENDING = 'sending';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';

    /**
     * Campaigns whose content and audience can still be edited.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::DRAFT, self::SCHEDULED], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this, [self::SCHEDULED, self::PREPARING, self::SENDING], true);
    }
}
