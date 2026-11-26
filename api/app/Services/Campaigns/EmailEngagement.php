<?php

namespace App\Services\Campaigns;

use App\Enums\MessageStatus;
use Illuminate\Database\Query\Builder;

/**
 * Unique open and click figures for a set of campaign emails, in one aggregate query.
 */
class EmailEngagement
{
    /**
     * Summarise the messages in the query that a provider accepted.
     *
     * @return array{emails: int, opened: int, open_rate: float, clicked: int, click_rate: float}
     */
    public function summarise(Builder $messages): array
    {
        $row = $messages
            ->whereIn('campaign_messages.status', [MessageStatus::SENT->value, MessageStatus::DELIVERED->value])
            ->selectRaw('COUNT(*) as emails')
            ->selectRaw('SUM(CASE WHEN campaign_messages.opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened')
            ->selectRaw('SUM(CASE WHEN campaign_messages.clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked')
            ->first();

        $emails = (int) $row->emails;

        return [
            'emails' => $emails,
            'opened' => (int) $row->opened,
            'open_rate' => static::rate((int) $row->opened, $emails),
            'clicked' => (int) $row->clicked,
            'click_rate' => static::rate((int) $row->clicked, $emails),
        ];
    }

    public static function rate(int|string|null $part, int|string|null $whole): float
    {
        return $whole ? round((int) $part / (int) $whole * 100, 1) : 0.0;
    }
}
