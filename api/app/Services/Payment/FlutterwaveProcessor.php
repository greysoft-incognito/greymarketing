<?php

namespace App\Services\Payment;

use App\Enums\HttpStatus;
use App\Helpers\Provider;
use App\Models\TempUser;
use App\Models\User;
use App\Services\CustomObject;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Valorin\Random\Random;

/**
 * Flutterwave Standard checkout: `intent` returns a hosted payment link, `verify` confirms by reference.
 */
class FlutterwaveProcessor implements PaymentInterface
{
    public const BASE_URL = 'https://api.flutterwave.com/v3';

    public function __construct(
        protected Request $request,
        protected User|TempUser|null $user = null
    ) {}

    /**
     * Create a hosted payment link. Reads `currency`, `redirect` and `meta` from the request.
     */
    public function intent(
        int|float $amount,
        ?callable $successCallback = null,
        ?callable $errorCallback = null,
        ?bool $respond = true
    ) {
        $reference = Provider::config('reference_prefix', 'TRX-') . Random::string(20, false, true, true, false);

        try {
            $data = $this->client()->post('payments', [
                'tx_ref' => $reference,
                'amount' => $amount,
                'currency' => $this->request->input('currency', Provider::config('app_currency', 'NGN')),
                'redirect_url' => $this->request->input('redirect', Provider::config('payment_verify_url')),
                'customer' => array_filter([
                    'email' => $this->user?->email,
                    'name' => $this->user?->fullname,
                    'phonenumber' => $this->user?->phone,
                ]),
                'customizations' => ['title' => Provider::config('app_name', config('app.name'))],
                'meta' => $this->request->input('meta'),
            ])->throw()->json('data');
        } catch (RequestException|ConnectionException $e) {
            return $this->failed($e, HttpStatus::BAD_REQUEST, $errorCallback);
        }

        $payload = new CustomObject(['link' => $data['link'] ?? null]);
        $message = 'Transaction initialized';

        if ($successCallback) {
            $response = $successCallback($reference, $payload, $amount, $message, HttpStatus::OK);

            if ($respond) {
                return $response;
            }
        }

        return new CustomObject([
            'amount' => $amount,
            'message' => $message,
            'payload' => $payload->toArray(),
            'reference' => $reference,
            'status_code' => HttpStatus::OK,
        ]);
    }

    /**
     * Look the transaction up by our reference. `payload` holds Flutterwave's transaction data.
     */
    public function verify(
        string $reference,
        ?callable $successCallback = null,
        ?callable $errorCallback = null,
        ?bool $respond = true
    ) {
        try {
            $data = $this->client()
                ->get('transactions/verify_by_reference', ['tx_ref' => $reference])
                ->throw()
                ->json('data');
        } catch (RequestException|ConnectionException $e) {
            return $this->failed($e, HttpStatus::UNPROCESSABLE_ENTITY, $errorCallback);
        }

        if (($data['status'] ?? null) !== 'successful') {
            return $this->failed(
                new \ErrorException('Payment could not be verified, status is ' . ($data['status'] ?? 'unknown')),
                HttpStatus::UNPROCESSABLE_ENTITY,
                $errorCallback
            );
        }

        $message = 'Transaction verified';

        if ($successCallback) {
            $response = $successCallback($reference, new CustomObject($data), $message, HttpStatus::OK);

            if ($respond) {
                return $response;
            }
        }

        return new CustomObject([
            'message' => $message,
            'payload' => $data,
            'status_code' => HttpStatus::OK,
            'payment_approved' => true,
        ]);
    }

    /**
     * Flutterwave has no endpoint for revoking a saved card token.
     */
    public function deauthorize(
        string $authorization_code,
        ?callable $successCallback = null,
        ?callable $errorCallback = null,
        ?bool $respond = true
    ) {
        return $this->failed(
            new \ErrorException('Flutterwave does not support revoking card authorizations.'),
            HttpStatus::BAD_REQUEST,
            $errorCallback
        );
    }

    /**
     * Pay out to the bank account in the user's `data.bank` (`bank_code`, `nuban`).
     */
    public function transfer(
        int|float $amount,
        ?string $reason = null,
        ?callable $successCallback = null,
        ?callable $errorCallback = null,
        ?bool $respond = true
    ) {
        $bank = $this->user?->data['bank'] ?? [];
        $reference = Provider::config('reference_prefix', 'TRX-') . Random::string(20, false, true, true, false);

        try {
            $data = $this->client()->post('transfers', [
                'account_bank' => $bank['bank_code'] ?? null,
                'account_number' => $bank['nuban'] ?? null,
                'amount' => $amount,
                'narration' => $reason,
                'currency' => $this->request->input('currency', Provider::config('app_currency', 'NGN')),
                'reference' => $reference,
            ])->throw()->json('data');
        } catch (RequestException|ConnectionException $e) {
            return $this->failed($e, HttpStatus::UNPROCESSABLE_ENTITY, $errorCallback);
        }

        $message = 'Transfer Completed';

        if ($successCallback) {
            $response = $successCallback(new CustomObject($data), $message, HttpStatus::OK);

            if ($respond) {
                return $response;
            }
        }

        return new CustomObject([
            'message' => $message,
            'payload' => $data,
            'reference' => $reference,
            'status_code' => HttpStatus::OK,
        ]);
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withToken((string) Provider::config('flutterwave_secret_key', env('FLUTTERWAVE_SECRET_KEY'), true))
            ->acceptJson()
            ->timeout(30);
    }

    protected function failed(\Throwable $e, HttpStatus $code, ?callable $errorCallback): mixed
    {
        $message = $e instanceof RequestException
            ? ($e->response->json('message') ?: $e->getMessage())
            : $e->getMessage();

        if ($errorCallback) {
            return $errorCallback($message, $code);
        }

        return new CustomObject([
            'error' => $code->value,
            'message' => $message,
            'status_code' => $code,
        ]);
    }
}
