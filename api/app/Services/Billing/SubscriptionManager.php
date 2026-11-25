<?php

namespace App\Services\Billing;

use App\Enums\HttpStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Traits\ControllerCanExtend;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Starts, pays for and confirms workspace subscriptions through Flutterwave.
 */
class SubscriptionManager
{
    use ControllerCanExtend;

    public const PAYMENT_METHOD = 'flutterwave';

    /**
     * Subscribe to a plan. Free plans start at once; paid plans return a checkout link.
     *
     * @return array{0: Subscription, 1: ?string} The subscription and the checkout link.
     */
    public function subscribe(Request $request, Workspace $workspace, Plan $plan, User $user): array
    {
        $subscription = $workspace->subscriptions()->make([
            'amount' => $plan->price,
            'currency' => $plan->currency,
        ]);
        $subscription->plan()->associate($plan);
        $subscription->user()->associate($user);
        $subscription->save();

        if ($plan->isFree()) {
            $subscription->activate();

            return [$subscription, null];
        }

        $request->merge(['currency' => $plan->currency]);

        $payment = $this->payWith(self::PAYMENT_METHOD)->makePayment($request, $user, (float) $plan->price, $subscription);

        return [$subscription, $payment['payload']['link'] ?? null];
    }

    /**
     * Confirm a payment with Flutterwave and activate its subscription. Safe to call more than once.
     */
    public function confirm(Transaction $transaction): Subscription
    {
        /** @var Subscription $subscription */
        $subscription = $transaction->transactable;

        abort_unless($subscription instanceof Subscription, HttpStatus::NOT_FOUND->value, 'No subscription for this payment.');

        if ($transaction->status === 'complete') {
            return $subscription;
        }

        $result = $this->payWith(self::PAYMENT_METHOD)
            ->resolvePaymentProcessor(request(), $transaction->user)
            ->verify($transaction->reference);

        $paid = $result['payload'] ?? null;
        $valid = ! isset($result['error'])
            && (float) ($paid['amount'] ?? 0) >= (float) $transaction->due
            && strtoupper((string) ($paid['currency'] ?? '')) === strtoupper($subscription->currency);

        if (! $valid) {
            $transaction->forceFill(['status' => 'rejected'])->save();

            abort(HttpStatus::UNPROCESSABLE_ENTITY->value, 'We were unable to verify this payment.');
        }

        DB::transaction(function () use ($transaction, $subscription) {
            $locked = Transaction::query()->whereKey($transaction->id)->lockForUpdate()->first();

            if ($locked->status === 'complete') {
                return;
            }

            $locked->forceFill(['status' => 'complete'])->save();

            // A checkout expired as abandoned still starts once its payment arrives.
            if (! $subscription->starts_at) {
                $subscription->activate();
            }
        });

        return $subscription->refresh();
    }

    /**
     * Find the workspace's Flutterwave payment for a reference.
     */
    public function transactionFor(Workspace $workspace, string $reference): Transaction
    {
        return Transaction::query()
            ->where('reference', $reference)
            ->where('method', self::PAYMENT_METHOD)
            ->whereHasMorph('transactable', Subscription::class, fn ($query) => $query->where('workspace_id', $workspace->id))
            ->firstOrFail();
    }
}
