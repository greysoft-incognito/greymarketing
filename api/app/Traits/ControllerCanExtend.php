<?php

namespace App\Traits;

use App\Enums\HttpStatus;
use App\Models\TempUser;
use App\Models\User;
use App\Services\Payment\FlutterwaveProcessor;
use App\Services\Payment\PaymentInterface;
use App\Services\Payment\PaystackProcessor;
use App\Services\Payment\StripeProcessor;
use App\Services\Payment\WalletProcessor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait ControllerCanExtend
{
    /**
     * Prefered Payment Processor
     *
     * @var string
     */
    protected $paymentProcessor = 'paystack';

    /**
     * Set the prefered payment processor
     */
    public function payWith(string $paymentProcessor = 'paystack'): static
    {
        $this->paymentProcessor = $paymentProcessor;

        return $this;
    }

    /**
     * The processor for the preferred payment method.
     *
     * @throws ValidationException
     */
    protected function resolvePaymentProcessor(Request $request, User|TempUser $user): PaymentInterface
    {
        return match ($this->paymentProcessor) {
            'paystack' => new PaystackProcessor($request, $user),
            'stripe' => new StripeProcessor($request, $user),
            'wallet' => new WalletProcessor($request, $user),
            'flutterwave' => new FlutterwaveProcessor($request, $user),
            default => throw ValidationException::withMessages([
                'payment_method' => 'Unknown Payment Method Selected.',
            ]),
        };
    }

    /**
     * Initiate a payment
     *
     * @throws ValidationException
     */
    public function makePayment(
        Request $request,
        User|TempUser $user,
        int|float $amount,
        ?Model $transactable = null,
        int|float $discount = 0,
    ): \Illuminate\Support\Collection {
        $fees = 0;

        $paymentIntent = $this->resolvePaymentProcessor($request, $user)->intent($amount - $discount);

        if (isset($paymentIntent['error'])) {
            $user instanceof TempUser && $user->delete();
            abort($paymentIntent['error'], $paymentIntent['message']);
        }

        // Create transaction
        /** @var \App\Models\Transaction */
        $transaction = $user->transactions()->make([
            'request_data' => $request->all(),
            'reference' => $paymentIntent['reference'] ?? $paymentIntent['id'],
            'discount' => $discount,
            'status' => 'pending',
            'method' => $this->paymentProcessor,
            'amount' => $amount,
            'fees' => $fees,
            'due' => ($amount - $discount) + $fees,
        ]);

        if ($transactable) {
            $transaction->transactable_type = $transactable->getMorphClass();
            $transaction->transactable_id = $transactable->id;
        }

        $transaction->save();

        return collect($paymentIntent->toArray());
    }

    /**
     * Verify a payment
     *
     * @throws ValidationException
     */
    public function verifyPayment(
        Request $request,
        User|TempUser $user,
        string $reference,
    ): \Illuminate\Support\Collection {
        $paymentIntent = $this->resolvePaymentProcessor($request, $user)->verify($reference);

        /** @var \App\Models\Transaction */
        $transaction = $user->transactions()->where('reference', $reference)->first();

        if (isset($paymentIntent['error'])) {
            if ($transaction) {
                $transaction->status = 'rejected';
                $transaction->save();
            }
            abort(HttpStatus::BAD_REQUEST->value, 'We were unable to verify this transaction.');
        }

        // Update the transaction status
        if ($transaction) {
            $transaction->status = 'complete';
            $transaction->save();
        }

        return collect($paymentIntent->toArray());
    }
}
