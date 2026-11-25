<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Toneflix\ResourceModifier\Services\Json\JsonResource;

/**
 * Wraps the array built by WorkspaceStats.
 */
class WorkspaceStatsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
