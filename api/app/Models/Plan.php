<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\PlanInterval;
use App\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;
    use HasPublicUlid;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'interval',
        'contacts_limit',
        'emails_limit',
        'sms_limit',
        'seats_limit',
        'is_active',
        'is_default',
        'sort',
    ];

    protected $attributes = [
        'currency' => 'NGN',
        'interval' => 'month',
        'is_active' => true,
        'is_default' => false,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'interval' => PlanInterval::class,
            'contacts_limit' => 'integer',
            'emails_limit' => 'integer',
            'sms_limit' => 'integer',
            'seats_limit' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Plan $plan) {
            if ($plan->is_default && $plan->wasChanged('is_default')) {
                static::query()->whereKeyNot($plan->getKey())->update(['is_default' => false]);
            }
        });
    }

    /**
     * The plan workspaces fall back to without a paid subscription.
     */
    public static function fallback(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isFree(): bool
    {
        return (float) $this->price <= 0;
    }

    /**
     * Messages allowed per month on a channel, or null for no limit.
     */
    public function messageLimit(Channel $channel): ?int
    {
        return match ($channel) {
            Channel::EMAIL => $this->emails_limit,
            Channel::SMS => $this->sms_limit,
        };
    }
}
