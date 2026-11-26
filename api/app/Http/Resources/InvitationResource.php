<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Toneflix\ResourceModifier\Services\Json\JsonResource;

class InvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'email' => $this->email,
            'role' => $this->role,
            'workspace' => $this->whenLoaded('workspace', fn () => [
                'id' => $this->workspace->ulid,
                'name' => $this->workspace->name,
            ]),
            'invited_by' => $this->whenLoaded('inviter', fn () => $this->inviter ? [
                'id' => $this->inviter->username,
                'name' => $this->inviter->fullname,
            ] : null),
            'is_expired' => $this->expires_at->isPast(),
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
        ];
    }
}
