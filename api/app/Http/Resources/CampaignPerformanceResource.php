<?php

namespace App\Http\Resources;

use App\Enums\Channel;
use Illuminate\Http\Request;

/**
 * A campaign with the delivery and open counts loaded by ReportController.
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
            ],
            'delivery_rate' => $reached ? round($this->delivered_count / $reached * 100, 1) : 0.0,
            'open_rate' => $email ? ($reached ? round($this->opened_count / $reached * 100, 1) : 0.0) : null,
        ];
    }
}
