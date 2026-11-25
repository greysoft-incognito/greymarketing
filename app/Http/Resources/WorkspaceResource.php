<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'role' => $this->whenPivotLoaded('workspace_user', fn () => $this->pivot->role),
            'is_owner' => $this->owner_id === $request->user()?->id,
            'created_at' => $this->created_at,
        ];
    }
}
