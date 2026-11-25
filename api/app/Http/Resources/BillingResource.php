<?php

namespace App\Http\Resources;

use App\Services\Billing\UsageLimits;
use Illuminate\Http\Request;
use Toneflix\ResourceModifier\Services\Json\JsonResource;

/**
 * A workspace's plan, subscription and usage.
 */
class BillingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $plan = $this->plan();

        return [
            'workspace' => ['id' => $this->ulid, 'name' => $this->name],
            'plan' => $plan ? new PlanResource($plan) : null,
            'subscription' => $this->currentSubscription ? new SubscriptionResource($this->currentSubscription) : null,
            'usage' => app(UsageLimits::class)->summary($this->resource),
            'period' => [
                'starts_at' => now()->startOfMonth(),
                'ends_at' => now()->endOfMonth(),
            ],
        ];
    }
}
