<?php

namespace App\Services\Campaigns;

use App\Enums\Channel;
use App\Models\Campaign;

/**
 * Builds a campaign's delivery report with aggregate queries.
 */
class CampaignReport
{
    public function __construct(
        protected EmailEngagement $engagement,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Campaign $campaign): array
    {
        $counts = $campaign->statusCounts();
        $attempted = $counts['sent'] + $counts['delivered'] + $counts['failed'];
        $reached = $counts['sent'] + $counts['delivered'];

        return [
            'status' => $campaign->status->value,
            'recipients' => $campaign->recipients_count,
            'counts' => $counts,
            'delivery_rate' => $reached ? round($counts['delivered'] / $reached * 100, 1) : 0.0,
            'failure_rate' => $attempted ? round($counts['failed'] / $attempted * 100, 1) : 0.0,
            'engagement' => $this->emailEngagement($campaign),
            'opt_outs' => $this->optOuts($campaign),
            'started_at' => $campaign->started_at,
            'completed_at' => $campaign->completed_at,
        ];
    }

    /**
     * Unique opens and clicks, or null for channels that cannot track them.
     *
     * @return array{emails: int, opened: int, open_rate: float, clicked: int, click_rate: float}|null
     */
    protected function emailEngagement(Campaign $campaign): ?array
    {
        if ($campaign->channel !== Channel::EMAIL) {
            return null;
        }

        return $this->engagement->summarise($campaign->messages()->toBase());
    }

    /**
     * Recipients who unsubscribed from the channel after the campaign started.
     */
    protected function optOuts(Campaign $campaign): int
    {
        if (! $campaign->started_at) {
            return 0;
        }

        return $campaign->messages()
            ->join('contacts', 'contacts.id', '=', 'campaign_messages.contact_id')
            ->where("contacts.{$campaign->channel->value}_unsubscribed_at", '>=', $campaign->started_at)
            ->count();
    }
}
