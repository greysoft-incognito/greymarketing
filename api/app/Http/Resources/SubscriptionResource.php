<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Toneflix\ResourceModifier\Services\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'status' => $this->status,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'cancelled_at' => $this->cancelled_at,
            'plan' => $this->whenLoaded('plan', fn () => new PlanResource($this->plan)),
            'workspace' => $this->whenLoaded('workspace', fn () => [
                'id' => $this->workspace->ulid,
                'name' => $this->workspace->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
