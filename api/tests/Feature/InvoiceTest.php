<?php

namespace Tests\Feature;

use App\Helpers\Provider;
use App\Models\Plan;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\Invoice;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        Provider::config(['flutterwave_secret_key' => 'FLWSECK_TEST-x', 'invoice_address' => "12 Allen Avenue\nIkeja, Lagos"]);

        Http::fake([
            'api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.test']]),
            'api.flutterwave.com/v3/transactions/verify_by_reference*' => Http::response([
                'status' => 'success',
                'data' => [
                    'status' => 'successful',
                    'amount' => 5000,
                    'currency' => 'NGN',
                    'card' => ['token' => 'flw-t1nf-x', 'type' => 'VISA', 'last_4digits' => '4242'],
                ],
            ]),
        ]);

        $this->workspace = Workspace::factory()->create(['name' => 'Acme Foods']);

        Sanctum::actingAs($this->workspace->owner);
    }

    protected function url(string $path): string
    {
        return "/api/workspaces/{$this->workspace->ulid}/billing{$path}";
    }

    protected function checkout(): Transaction
    {
        $this->postJson($this->url('/subscribe'), ['plan' => Plan::firstWhere('slug', 'starter')->ulid])->assertCreated();

        return Transaction::query()->latest('id')->first();
    }

    public function testCompletedPaymentsHaveADownloadableInvoice(): void
    {
        $transaction = $this->checkout();
        $this->postJson($this->url('/verify'), ['reference' => $transaction->reference])->assertOk();

        $invoice = new Invoice($transaction->fresh());

        $this->getJson($this->url('/transactions'))->assertJsonPath('data.0.invoice_number', $invoice->number());
        $response = $this->get($this->url("/transactions/{$transaction->reference}/invoice"));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString($invoice->filename(), $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $data = $invoice->data();
        $this->assertSame('Acme Foods', $data['customer']['workspace']);
        $this->assertSame('NGN 5,000.00', $data['total']);
        $this->assertSame('Flutterwave, VISA ending 4242', $data['method']);
        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{6}$/', $data['number']);
    }

    public function testPendingPaymentsHaveNoInvoice(): void
    {
        $transaction = $this->checkout();

        $this->getJson($this->url('/transactions'))->assertJsonPath('data.0.invoice_number', null);
        $this->getJson($this->url("/transactions/{$transaction->reference}/invoice"))->assertNotFound();
    }

    public function testInvoicesAreOnlyForOwnersAndAdminsOfTheWorkspace(): void
    {
        $transaction = $this->checkout();
        $this->postJson($this->url('/verify'), ['reference' => $transaction->reference])->assertOk();

        $member = User::factory()->create();
        $this->workspace->members()->attach($member, ['role' => 'member']);
        Sanctum::actingAs($member);
        $this->getJson($this->url("/transactions/{$transaction->reference}/invoice"))->assertForbidden();

        $other = Workspace::factory()->create();
        Sanctum::actingAs($other->owner);
        $this->getJson("/api/workspaces/{$other->ulid}/billing/transactions/{$transaction->reference}/invoice")->assertNotFound();
    }
}
