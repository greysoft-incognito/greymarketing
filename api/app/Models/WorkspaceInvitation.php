<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use App\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An emailed invite to join a workspace. Only a hash of the link token is stored.
 */
class WorkspaceInvitation extends Model
{
    use HasPublicUlid;

    public const LIFETIME_DAYS = 7;

    protected $fillable = [
        'email',
        'role',
    ];

    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
            'expires_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * The pending invite behind a link token, if it still works.
     */
    public static function findPending(string $token): ?self
    {
        return static::query()->pending()->where('token', static::hashToken($token))->first();
    }

    /**
     * Give the invite a fresh token and expiry, returning the plain token for the link.
     */
    public function refreshToken(): string
    {
        $token = Str::random(48);

        $this->token = static::hashToken($token);
        $this->expires_at = now()->addDays(static::LIFETIME_DAYS);

        return $token;
    }

    public function scopePending(Builder $query): void
    {
        $query->where('expires_at', '>', now());
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
