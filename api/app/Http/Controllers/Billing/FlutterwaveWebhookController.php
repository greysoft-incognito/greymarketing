<?php

namespace App\Http\Controllers\Billing;

use App\Enums\HttpStatus;
use App\Helpers\Provider;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Http\Request;

/**
 * Activates subscriptions when Flutterwave reports a completed charge. The payment is always re-verified.
 */
class FlutterwaveWebhookController extends Controller
{
    public function __invoke(Request $request, SubscriptionManager $manager)
    {
        $secret = (string) Provider::config('flutterwave_webhook_hash', env('FLUTTERWAVE_WEBHOOK_HASH'), true);

        abort_unless(
            $secret !== '' && hash_equals($secret, (string) $request->header('verif-hash')),
            HttpStatus::UNAUTHORIZED->value,
            'Invalid webhook signature.'
        );

        $reference = $request->input('data.tx_ref');

        if ($request->input('event') !== 'charge.completed' || ! $reference) {
            return response()->noContent();
        }

        $transaction = Transaction::query()
            ->where('reference', $reference)
            ->where('method', SubscriptionManager::PAYMENT_METHOD)
            ->first();

        if ($transaction) {
            $manager->confirm($transaction);
        }

        return response()->noContent();
    }
}
