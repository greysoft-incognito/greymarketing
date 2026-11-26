<?php

namespace App\Services\Payment;

/**
 * A payment processor that can save a card from a payment and charge it again later.
 */
interface ChargesSavedCards
{
    /**
     * The reusable card from a verified payment's payload, or null when it was not paid by card.
     *
     * @param  array<string, mixed>  $payment
     * @return array{token: string, email: ?string, brand: ?string, last4: ?string, expiry: ?string, country: ?string}|null
     */
    public function savedCard(array $payment): ?array;

    /**
     * Charge a saved card. The result holds `reference` on success, or `error` and `message` on failure.
     *
     * @param  array{token: string, email: ?string, country: ?string}  $card
     * @return \App\Services\CustomObject
     */
    public function chargeSavedCard(array $card, int|float $amount, string $currency, ?string $narration = null);
}
