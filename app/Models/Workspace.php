<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use App\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasFactory;
    use HasPublicUlid;

    protected $fillable = [
        'name',
    ];

    /**
     * Create a workspace owned by the given user.
     */
    public static function createFor(User $user, ?string $name = null): self
    {
        $workspace = new self(['name' => $name ?? "{$user->firstname}'s Workspace"]);
        $workspace->owner()->associate($user);
        $workspace->save();

        $workspace->members()->attach($user, ['role' => WorkspaceRole::OWNER->value]);

        return $workspace;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')->withPivot('role')->withTimestamps();
    }

    public function connections(): HasMany
    {
        return $this->hasMany(ProviderConnection::class);
    }

    /**
     * The user's role in this workspace, or null when they are not a member.
     */
    public function roleOf(User $user): ?WorkspaceRole
    {
        $role = $this->members()->whereKey($user->id)->value('workspace_user.role');

        return $role ? WorkspaceRole::from($role) : null;
    }
}
