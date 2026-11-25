<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Toneflix\ResourceModifier\Services\Json\JsonResource;

class CampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'channel' => $this->channel,
            'status' => $this->status,
            'template' => $this->whenLoaded('template', fn () => $this->template ? new TemplateResource($this->template) : null),
            'connection' => $this->whenLoaded('providerConnection', fn () => $this->providerConnection ? new ProviderConnectionResource($this->providerConnection) : null),
            'lists' => ContactListResource::collection($this->whenLoaded('lists')),
            'recipients_count' => $this->recipients_count,
            'scheduled_at' => $this->scheduled_at,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
        ];
    }
}
