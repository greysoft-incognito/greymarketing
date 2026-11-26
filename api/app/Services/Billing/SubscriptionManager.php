<?php

namespace App\Services\Billing;

use App\Enums\HttpStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\RenewalFailed;
use App\Services\Payment\ChargesSavedCards;
use App\Traits\ControllerCanExtend;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Starts, pays for, confirms and renews workspace subscriptions through Flutterwave.
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

        $processor = $this->payWith(self::PAYMENT_METHOD)->resolvePaymentProcessor(request(), $transaction->user);
        $result = $processor->verify($transaction->reference);

        $paid = $result['payload'] ?? null;
        $valid = ! isset($result['error'])
            && (float) ($paid['amount'] ?? 0) >= (float) $transaction->due
            && strtoupper((string) ($paid['currency'] ?? '')) === strtoupper($subscription->currency);

        if (! $valid) {
            $transaction->forceFill(['status' => 'rejected'])->save();

            abort(HttpStatus::UNPROCESSABLE_ENTITY->value, 'We were unable to verify this payment.');
        }

        DB::transaction(function () use ($transaction, $subscription, $processor, $paid) {
            $locked = Transaction::query()->whereKey($transaction->id)->lockForUpdate()->first();

            if ($locked->status === 'complete') {
                return;
            }

            $locked->forceFill(['status' => 'complete'])->save();

            if ($processor instanceof ChargesSavedCards) {
                $this->rememberCard($subscription, $processor->savedCard($paid));
            }

            // A checkout expired as abandoned still starts once its payment arrives.
            if (! $subscription->starts_at) {
                $subscription->activate();
            }
        });

        return $subscription->refresh();
    }

    /**
     * Pay for the next period with the subscription's saved card.
     *
     * @return Subscription|null The renewal once paid, or null when the charge failed.
     */
    public function renew(Subscription $current): ?Subscription
    {
        $plan = $current->plan;
        $user = $current->user ?? $current->workspace->owner;

        $current->recordRenewalAttempt();

        if (! $plan->is_active || $plan->isFree() || ! $user) {
            $current->forceFill(['auto_renew' => false])->save();

            return null;
        }

        $renewal = $current->workspace->subscriptions()->make([
            'amount' => $plan->price,
            'currency' => $plan->currency,
        ]);
        $renewal->plan()->associate($plan);
        $renewal->user()->associate($user);
        $renewal->forceFill(['auto_renew' => true, 'card' => $current->card])->save();

        $transaction = $this->payWith(self::PAYMENT_METHOD)
            ->chargeSavedCard(request(), $user, $current->card, (float) $plan->price, $plan->currency, $renewal);

        try {
            abort_if($transaction->status === 'rejected', HttpStatus::PAYMENT_REQUIRED->value, $transaction->request_data['message'] ?? 'The card was declined.');

            return $this->confirm($transaction);
        } catch (HttpException $e) {
            $renewal->forceFill(['status' => SubscriptionStatus::EXPIRED])->save();

            if (! $current->hasRenewalAttemptsLeft()) {
                $user->notify(new RenewalFailed($current, $e->getMessage()));
            }

            return null;
        }
    }

    /**
     * Turn renewal with the saved card on or off. Turning it on undoes a cancellation.
     */
    public function setAutoRenew(Subscription $subscription, bool $enabled): Subscription
    {
        if (! $enabled) {
            $subscription->forceFill(['auto_renew' => false])->save();

            return $subscription;
        }

        abort_if($subscription->plan->isFree(), HttpStatus::CONFLICT->value, 'Free plans do not renew.');
        abort_unless($subscription->card, HttpStatus::UNPROCESSABLE_ENTITY->value, 'Pay for your plan by card once to turn on auto-renewal.');

        $subscription->enableAutoRenew();

        return $subscription;
    }

    /**
     * Keep the card a payment was made with, and renew with it unless renewal was already decided.
     *
     * @param  array<string, mixed>|null  $card
     */
    protected function rememberCard(Subscription $subscription, ?array $card): void
    {
        if (! $card || $subscription->plan->isFree()) {
            return;
        }

        $subscription->forceFill([
            'auto_renew' => $subscription->card ? $subscription->auto_renew : true,
            'card' => $card,
        ])->save();
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
