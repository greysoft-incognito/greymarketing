<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Charges saved cards for subscriptions that end within a day and are set to renew.
 */
class RenewSubscriptions extends Command
{
    protected $signature = 'billing:renew-subscriptions';

    protected $description = 'Renew subscriptions with their saved cards';

    public function handle(SubscriptionManager $manager): int
    {
        $renewed = 0;
        $failed = 0;

        Subscription::query()
            ->dueForRenewal()
            ->with(['plan', 'user', 'workspace.owner'])
            ->chunkById(100, function (Collection $subscriptions) use ($manager, &$renewed, &$failed) {
                foreach ($subscriptions as $subscription) {
                    try {
                        $manager->renew($subscription) ? $renewed++ : $failed++;
                    } catch (\Throwable $e) {
                        $failed++;
                        report($e);
                    }
                }
            });

        $this->info("Renewed {$renewed} subscriptions, {$failed} failed.");

        return self::SUCCESS;
    }
}
