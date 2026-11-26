<?php

namespace App\Http\Resources;

use App\Enums\SubscriptionStatus;
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
            'auto_renew' => (bool) $this->auto_renew,
            'renews_at' => $this->auto_renew && $this->status === SubscriptionStatus::ACTIVE ? $this->ends_at : null,
            'card' => $this->card ? [
                'brand' => $this->card['brand'] ?? null,
                'last4' => $this->card['last4'] ?? null,
                'expiry' => $this->card['expiry'] ?? null,
            ] : null,
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
