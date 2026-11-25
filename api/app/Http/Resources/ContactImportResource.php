<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Toneflix\ResourceModifier\Services\Json\JsonResource;

class ContactImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'status' => $this->status,
            'mapping' => $this->mapping,
            'list' => new ContactListResource($this->whenLoaded('contactList')),
            'processed' => $this->processed,
            'imported' => $this->imported,
            'skipped' => $this->skipped,
            'failures' => $this->failures ?? [],
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
        ];
    }
}
