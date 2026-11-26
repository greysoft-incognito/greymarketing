<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Toneflix\ResourceModifier\Services\Json\JsonResource;

class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->username,
            'name' => $this->fullname,
            'email' => $this->email,
            'role' => $this->pivot?->role,
            'is_you' => $this->id === $request->user()?->id,
            'joined_at' => $this->pivot?->created_at,
        ];
    }
}
