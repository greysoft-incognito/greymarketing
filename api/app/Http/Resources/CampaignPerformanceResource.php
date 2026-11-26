<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * A campaign with the delivery counts loaded by ReportController.
 */
class CampaignPerformanceResource extends CampaignResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reached = $this->sent_count + $this->delivered_count;

        return parent::toArray($request) + [
            'counts' => [
                'sent' => $reached,
                'delivered' => $this->delivered_count,
                'failed' => $this->failed_count,
            ],
            'delivery_rate' => $reached ? round($this->delivered_count / $reached * 100, 1) : 0.0,
        ];
    }
}
