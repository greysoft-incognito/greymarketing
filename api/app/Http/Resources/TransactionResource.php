<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Toneflix\ResourceModifier\Services\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'method' => $this->method,
            'status' => $this->status,
            'amount' => (float) $this->amount,
            'discount' => (float) $this->discount,
            'due' => (float) $this->due,
            'type' => $this->type,
            'subscription' => $this->whenLoaded('transactable', fn () => $this->transactable instanceof Subscription ? [
                'id' => $this->transactable->ulid,
                'currency' => $this->transactable->currency,
                'plan' => [
                    'name' => $this->transactable->plan->name,
                    'interval' => $this->transactable->plan->interval,
                ],
            ] : null),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->username,
                'name' => $this->user->fullname,
                'email' => $this->user->email,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
