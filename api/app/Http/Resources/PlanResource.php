<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Toneflix\ResourceModifier\Services\Json\JsonResource;

class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => (float) $this->price,
            'currency' => $this->currency,
            'interval' => $this->interval,
            'limits' => [
                'contacts' => $this->contacts_limit,
                'emails' => $this->emails_limit,
                'sms' => $this->sms_limit,
            ],
            'is_free' => $this->isFree(),
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'sort' => $this->sort,
            'active_subscriptions_count' => $this->whenHas('active_subscriptions_count'),
        ];
    }
}
