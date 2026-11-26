<?php

namespace App\Services\Billing;

use App\Enums\HttpStatus;
use App\Helpers\Provider;
use App\Models\Subscription;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * The PDF invoice for a completed subscription payment.
 */
class Invoice
{
    protected Subscription $subscription;

    public function __construct(protected Transaction $transaction)
    {
        abort_unless(
            $transaction->status === 'complete' && $transaction->transactable instanceof Subscription,
            HttpStatus::NOT_FOUND->value,
            'Invoices are only issued for completed payments.'
        );

        $this->subscription = $transaction->transactable->loadMissing('plan', 'workspace');
    }

    /**
     * The invoice number for a completed subscription payment, or null when it has no invoice.
     */
    public static function numberFor(Transaction $transaction): ?string
    {
        if ($transaction->status !== 'complete' || $transaction->transactable_type !== (new Subscription())->getMorphClass()) {
            return null;
        }

        return 'INV-' . $transaction->created_at->format('Y') . '-' . str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT);
    }

    public function number(): string
    {
        return self::numberFor($this->transaction);
    }

    public function filename(): string
    {
        return $this->number() . '.pdf';
    }

    /**
     * Everything the invoice view prints.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $plan = $this->subscription->plan;
        $card = $this->subscription->card;
        $user = $this->transaction->user;

        return [
            'number' => $this->number(),
            'issued_at' => $this->transaction->created_at,
            'paid_at' => $this->transaction->updated_at,
            'reference' => $this->transaction->reference,
            'seller' => [
                'name' => Provider::config('app_name', config('app.name')),
                'email' => Provider::config('app_email'),
                'address' => Provider::config('invoice_address'),
            ],
            'customer' => [
                'workspace' => $this->subscription->workspace->name,
                'name' => $user?->fullname,
                'email' => $user?->email,
            ],
            'item' => [
                'description' => $plan->name . ' plan, ' . ($plan->interval->value === 'year' ? 'yearly' : 'monthly'),
                'period' => $this->subscription->starts_at && $this->subscription->ends_at
                    ? $this->subscription->starts_at->toFormattedDateString() . ' to ' . $this->subscription->ends_at->toFormattedDateString()
                    : null,
            ],
            'amount' => $this->money($this->transaction->amount),
            'discount' => $this->transaction->discount > 0 ? $this->money($this->transaction->discount) : null,
            'total' => $this->money($this->transaction->due),
            'method' => $card
                ? 'Flutterwave, ' . trim(($card['brand'] ?? 'card') . ' ending ' . ($card['last4'] ?? ''))
                : 'Flutterwave',
        ];
    }

    public function download(): Response
    {
        return Pdf::loadView('billing.invoice', $this->data())->setPaper('a4')->download($this->filename());
    }

    protected function money(float $amount): string
    {
        return $this->subscription->currency . ' ' . number_format($amount, 2);
    }
}
