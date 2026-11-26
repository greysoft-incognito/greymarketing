<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Toneflix\ResourceModifier\Services\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'default_country' => $this->default_country,
            'role' => $this->whenPivotLoaded('workspace_user', fn () => $this->pivot->role),
            'is_owner' => $this->owner_id === $request->user()?->id,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? [
                'id' => $this->owner->username,
                'name' => $this->owner->fullname,
                'email' => $this->owner->email,
            ] : null),
            'subscription' => $this->whenLoaded('currentSubscription', fn () => $this->currentSubscription
                ? new SubscriptionResource($this->currentSubscription)
                : null),
            'plan' => $this->when($this->resource->relationLoaded('currentSubscription'), fn () => ($plan = $this->plan()) ? [
                'id' => $plan->slug,
                'name' => $plan->name,
            ] : null),
            'contacts_count' => $this->whenCounted('contacts'),
            'campaigns_count' => $this->whenCounted('campaigns'),
            'usage_this_month' => $this->when($this->hasAttribute('emails_this_month'), fn () => [
                'emails' => (int) $this->emails_this_month,
                'sms' => (int) $this->sms_this_month,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
