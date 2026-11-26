<?php

namespace App\Services\Campaigns;

use App\Enums\Channel;
use App\Models\Campaign;

/**
 * Builds a campaign's delivery report with aggregate queries.
 */
class CampaignReport
{
    /**
     * @return array<string, mixed>
     */
    public function build(Campaign $campaign): array
    {
        $counts = $campaign->statusCounts();
        $attempted = $counts['sent'] + $counts['delivered'] + $counts['failed'];
        $reached = $counts['sent'] + $counts['delivered'];
        $opened = $this->opened($campaign);

        return [
            'status' => $campaign->status->value,
            'recipients' => $campaign->recipients_count,
            'counts' => $counts,
            'delivery_rate' => $reached ? round($counts['delivered'] / $reached * 100, 1) : 0.0,
            'failure_rate' => $attempted ? round($counts['failed'] / $attempted * 100, 1) : 0.0,
            'opened' => $opened,
            'open_rate' => $opened === null ? null : ($reached ? round($opened / $reached * 100, 1) : 0.0),
            'opt_outs' => $this->optOuts($campaign),
            'started_at' => $campaign->started_at,
            'completed_at' => $campaign->completed_at,
        ];
    }

    /**
     * Recipients who opened the email at least once, or null for channels that cannot track opens.
     */
    protected function opened(Campaign $campaign): ?int
    {
        if ($campaign->channel !== Channel::EMAIL) {
            return null;
        }

        return $campaign->messages()->whereNotNull('opened_at')->count();
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
