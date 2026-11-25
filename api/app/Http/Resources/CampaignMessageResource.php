<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Toneflix\ResourceModifier\Services\Json\JsonResource;

class CampaignMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'to' => $this->to,
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? new ContactResource($this->contact) : null),
            'status' => $this->status,
            'provider_message_id' => $this->provider_message_id,
            'error' => $this->error,
            'attempts' => $this->attempts,
            'sent_at' => $this->sent_at,
            'delivered_at' => $this->delivered_at,
        ];
    }
}
