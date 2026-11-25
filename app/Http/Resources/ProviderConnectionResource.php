<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Toneflix\ResourceModifier\Services\Json\JsonResource;

class ProviderConnectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'provider' => $this->provider,
            'channel' => $this->channel,
            'name' => $this->name,
            'credentials' => $this->maskedCredentials(),
            'settings' => $this->settings ?? (object) [],
            'is_default' => $this->is_default,
            'status' => $this->status,
            'last_error' => $this->last_error,
            'last_tested_at' => $this->last_tested_at,
            'created_at' => $this->created_at,
        ];
    }
}
