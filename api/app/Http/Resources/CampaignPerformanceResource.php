<?php

namespace App\Http\Resources;

use App\Enums\Channel;
use App\Services\Campaigns\EmailEngagement;
use Illuminate\Http\Request;

/**
 * A campaign with the delivery, open and click counts loaded by ReportController.
 */
class CampaignPerformanceResource extends CampaignResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reached = $this->sent_count + $this->delivered_count;
        $email = $this->channel === Channel::EMAIL;

        return parent::toArray($request) + [
            'counts' => [
                'sent' => $reached,
                'delivered' => $this->delivered_count,
                'failed' => $this->failed_count,
                'opened' => $email ? $this->opened_count : null,
                'clicked' => $email ? $this->clicked_count : null,
            ],
            'delivery_rate' => $reached ? round($this->delivered_count / $reached * 100, 1) : 0.0,
            'open_rate' => $email ? EmailEngagement::rate($this->opened_count, $reached) : null,
            'click_rate' => $email ? EmailEngagement::rate($this->clicked_count, $reached) : null,
        ];
    }
}
