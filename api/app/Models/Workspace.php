<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use App\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Workspace extends Model
{
    use HasFactory;
    use HasPublicUlid;

    protected $fillable = [
        'name',
        'default_country',
    ];

    protected $attributes = [
        'default_country' => 'NG',
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

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function lists(): HasMany
    {
        return $this->hasMany(ContactList::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(ContactImport::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * The subscription giving access to a plan right now, if any.
     */
    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->ofMany(
            ['ends_at' => 'max', 'id' => 'max'],
            fn ($query) => $query->current()
        );
    }

    public function usage(): HasMany
    {
        return $this->hasMany(WorkspaceUsage::class);
    }

    /**
     * The plan whose limits apply: the current subscription's, else the default plan.
     */
    public function plan(): ?Plan
    {
        return $this->currentSubscription?->plan ?? Plan::fallback();
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
