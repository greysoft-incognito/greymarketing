<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Console\Command;

/**
 * Marks subscriptions past their end date as expired, and drops checkouts left unpaid for a day.
 */
class ExpireSubscriptions extends Command
{
    protected $signature = 'billing:expire-subscriptions';

    protected $description = 'Expire ended subscriptions and abandoned checkouts';

    public function handle(): int
    {
        $ended = Subscription::query()
            ->whereIn('status', SubscriptionStatus::current())
            ->where('ends_at', '<=', now())
            ->update(['status' => SubscriptionStatus::EXPIRED, 'updated_at' => now()]);

        $abandoned = Subscription::query()
            ->where('status', SubscriptionStatus::PENDING)
            ->where('created_at', '<=', now()->subDay())
            ->update(['status' => SubscriptionStatus::EXPIRED, 'updated_at' => now()]);

        $this->info("Expired {$ended} ended subscriptions and {$abandoned} abandoned checkouts.");

        return self::SUCCESS;
    }
}
