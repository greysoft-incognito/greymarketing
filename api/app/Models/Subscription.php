<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class Subscription extends Model
{
    use HasPublicUlid;

    /**
     * Renewal charges tried before the subscription is left to expire.
     */
    public const RENEWAL_ATTEMPTS = 3;

    protected $fillable = [
        'status',
        'amount',
        'currency',
        'starts_at',
        'ends_at',
    ];

    protected $hidden = [
        'card',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'amount' => 'decimal:2',
            'auto_renew' => 'boolean',
            'card' => 'encrypted:array',
            'renewal_attempts' => 'integer',
            'renewal_attempted_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'transactable');
    }

    /**
     * Subscriptions that give access to their plan right now.
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereIn('status', SubscriptionStatus::current())->where('ends_at', '>', now());
    }

    /**
     * Active subscriptions set to renew that end within a day and are due another charge attempt.
     */
    public function scopeDueForRenewal(Builder $query): void
    {
        $query->where('auto_renew', true)
            ->where('status', SubscriptionStatus::ACTIVE)
            ->whereNotNull('card')
            ->whereBetween('ends_at', [now(), now()->addDay()])
            ->where('renewal_attempts', '<', self::RENEWAL_ATTEMPTS)
            ->where(fn ($query) => $query->whereNull('renewal_attempted_at')->orWhere('renewal_attempted_at', '<=', now()->subHours(6)));
    }

    /**
     * Start the subscription. Renewing the current plan extends it; any other plan replaces it now.
     */
    public function activate(): void
    {
        DB::transaction(function () {
            $current = $this->workspace->subscriptions()->current()->whereKeyNot($this->id)->lockForUpdate()->first();
            $start = now();

            if ($current?->plan_id === $this->plan_id) {
                $start = $current->ends_at;
            }

            $current?->forceFill(['status' => SubscriptionStatus::EXPIRED, 'ends_at' => $start])->save();

            $this->forceFill([
                'status' => SubscriptionStatus::ACTIVE,
                'starts_at' => $start,
                'ends_at' => $this->ends_at ?? $this->plan->interval->periodEnd($start),
                'cancelled_at' => null,
            ])->save();
        });
    }

    /**
     * Stop renewal; the plan stays available until `ends_at`.
     */
    public function cancel(): void
    {
        $this->forceFill(['status' => SubscriptionStatus::CANCELLED, 'cancelled_at' => now(), 'auto_renew' => false])->save();
    }

    /**
     * Charge the saved card for the next period, undoing a cancellation.
     */
    public function enableAutoRenew(): void
    {
        $this->forceFill([
            'status' => SubscriptionStatus::ACTIVE,
            'cancelled_at' => null,
            'auto_renew' => true,
            'renewal_attempts' => 0,
            'renewal_attempted_at' => null,
        ])->save();
    }

    /**
     * Record a renewal charge attempt.
     */
    public function recordRenewalAttempt(): void
    {
        $this->forceFill([
            'renewal_attempts' => $this->renewal_attempts + 1,
            'renewal_attempted_at' => now(),
        ])->save();
    }

    public function hasRenewalAttemptsLeft(): bool
    {
        return $this->renewal_attempts < self::RENEWAL_ATTEMPTS;
    }
}
