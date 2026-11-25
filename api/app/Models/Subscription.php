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

    protected $fillable = [
        'status',
        'amount',
        'currency',
        'starts_at',
        'ends_at',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'amount' => 'decimal:2',
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
        $this->forceFill(['status' => SubscriptionStatus::CANCELLED, 'cancelled_at' => now()])->save();
    }
}
