<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Toneflix\ResourceModifier\Services\Json\JsonResource;

class ContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'email' => $this->email,
            'phone' => $this->phone,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'fields' => $this->fields ?? (object) [],
            'email_subscribed' => $this->email_unsubscribed_at === null,
            'sms_subscribed' => $this->sms_unsubscribed_at === null,
            'email_unsubscribed_at' => $this->email_unsubscribed_at,
            'sms_unsubscribed_at' => $this->sms_unsubscribed_at,
            'lists' => ContactListResource::collection($this->whenLoaded('lists')),
            'created_at' => $this->created_at,
        ];
    }
}
