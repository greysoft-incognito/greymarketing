<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Toneflix\ResourceModifier\Services\Json\JsonResource;

/**
 * A provider catalog entry from ProviderManager::catalog().
 */
class ProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource['key'],
            'name' => $this->resource['name'],
            'channels' => $this->resource['channels'],
            'credentials' => $this->resource['credentials'],
            'settings' => $this->resource['settings'],
        ];
    }
}
