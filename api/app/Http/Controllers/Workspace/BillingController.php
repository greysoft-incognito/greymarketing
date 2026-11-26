<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\HttpStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\BillingResource;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\TransactionCollection;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Services\Billing\Invoice;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    /**
     * The workspace's plan, subscription and usage this month.
     */
    public function show(Workspace $workspace)
    {
        $this->authorize('view', $workspace);

        return (new BillingResource($workspace->load('currentSubscription.plan')))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    /**
     * Start a subscription. Paid plans return a Flutterwave `checkout_url` to send the user to.
     */
    public function subscribe(Request $request, Workspace $workspace, SubscriptionManager $manager)
    {
        $this->authorize('manage', $workspace);

        $valid = $this->validate($request, [
            'plan' => ['required', 'string'],
            'redirect' => ['nullable', 'url'],
        ]);

        $plan = Plan::query()->active()->where('ulid', $valid['plan'])->first();

        abort_unless($plan, HttpStatus::UNPROCESSABLE_ENTITY->value, 'Choose an available plan.');

        [$subscription, $checkoutUrl] = $manager->subscribe($request, $workspace, $plan, $request->user());

        return (new SubscriptionResource($subscription->load('plan')))->additional([
            'checkout_url' => $checkoutUrl,
            'message' => $checkoutUrl ? 'Complete the payment to start your plan.' : 'Your plan is active.',
            'status' => 'success',
            'status_code' => HttpStatus::CREATED,
        ])->response()->setStatusCode(HttpStatus::CREATED->value);
    }

    /**
     * Confirm a payment after Flutterwave redirects back with `tx_ref`.
     */
    public function verify(Request $request, Workspace $workspace, SubscriptionManager $manager)
    {
        $this->authorize('manage', $workspace);

        $valid = $this->validate($request, [
            'reference' => ['required', 'string', 'max:100'],
        ]);

        $subscription = $manager->confirm($manager->transactionFor($workspace, $valid['reference']));

        return (new SubscriptionResource($subscription->load('plan')))->additional([
            'message' => 'Your plan is active.',
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    /**
     * Stop renewal. The plan stays available until the end of the paid period.
     */
    public function cancel(Workspace $workspace)
    {
        $this->authorize('manage', $workspace);

        /** @var Subscription|null $subscription */
        $subscription = $workspace->currentSubscription;

        abort_unless(
            $subscription?->status === SubscriptionStatus::ACTIVE,
            HttpStatus::CONFLICT->value,
            'There is no active subscription to cancel.'
        );

        $subscription->cancel();

        return (new SubscriptionResource($subscription->load('plan')))->additional([
            'message' => "Your plan stays active until {$subscription->ends_at->toFormattedDayDateString()}.",
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    /**
     * Turn renewal with the saved card on or off.
     */
    public function autoRenew(Request $request, Workspace $workspace, SubscriptionManager $manager)
    {
        $this->authorize('manage', $workspace);

        $valid = $this->validate($request, [
            'enabled' => ['required', 'boolean'],
        ]);

        /** @var Subscription|null $subscription */
        $subscription = $workspace->currentSubscription;

        abort_unless($subscription, HttpStatus::CONFLICT->value, 'There is no subscription to renew.');

        $subscription = $manager->setAutoRenew($subscription->load('plan'), (bool) $valid['enabled']);

        return (new SubscriptionResource($subscription))->additional([
            'message' => $subscription->auto_renew
                ? "Your plan renews automatically on {$subscription->ends_at->toFormattedDayDateString()}."
                : "Auto-renewal is off. Your plan ends on {$subscription->ends_at->toFormattedDayDateString()}.",
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    /**
     * Payments for the workspace's subscriptions, with the plan each one paid for.
     */
    public function transactions(Request $request, Workspace $workspace)
    {
        $this->authorize('manage', $workspace);

        $transactions = Transaction::query()
            ->whereHasMorph('transactable', Subscription::class, fn ($query) => $query->where('workspace_id', $workspace->id))
            ->with('transactable.plan')
            ->latest('id')
            ->paginate(min((int) $request->input('limit', 30), 100));

        return (new TransactionCollection($transactions))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    /**
     * Download the PDF invoice for a completed payment.
     */
    public function invoice(Workspace $workspace, string $reference, SubscriptionManager $manager)
    {
        $this->authorize('manage', $workspace);

        return (new Invoice($manager->transactionFor($workspace, $reference)))->download();
    }
}
