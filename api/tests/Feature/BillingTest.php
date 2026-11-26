<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Helpers\Provider;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingTest extends TestCase
{
    protected Workspace $workspace;

    protected Plan $starter;

    protected function setUp(): void
    {
        parent::setUp();

        Provider::config(['flutterwave_secret_key' => 'FLWSECK_TEST-x', 'flutterwave_webhook_hash' => 'hook-secret']);

        $this->workspace = Workspace::factory()->create();
        $this->starter = Plan::firstWhere('slug', 'starter');

        Sanctum::actingAs($this->workspace->owner);
    }

    protected function url(string $path): string
    {
        return "/api/workspaces/{$this->workspace->ulid}/billing{$path}";
    }

    protected function fakeFlutterwave(float $paid = 5000, string $currency = 'NGN', string $status = 'successful'): void
    {
        Http::fake([
            'api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'success',
                'data' => ['link' => 'https://checkout.flutterwave.com/v3/hosted/pay/abc'],
            ]),
            'api.flutterwave.com/v3/transactions/verify_by_reference*' => Http::response([
                'status' => 'success',
                'data' => ['id' => 99, 'status' => $status, 'amount' => $paid, 'currency' => $currency],
            ]),
        ]);
    }

    /**
     * Start a Starter checkout and return its payment reference.
     */
    protected function checkout(): string
    {
        $this->postJson($this->url('/subscribe'), ['plan' => $this->starter->ulid, 'redirect' => 'https://app.test/billing'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('checkout_url', 'https://checkout.flutterwave.com/v3/hosted/pay/abc');

        return Transaction::query()->latest('id')->value('reference');
    }

    public function testPlansAreListedPublicly(): void
    {
        Plan::factory()->create(['is_active' => false]);

        $this->app['auth']->forgetGuards();

        $this->getJson('/api/plans')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.slug', 'free')
            ->assertJsonPath('data.1.limits.contacts', 5000);
    }

    public function testFreePlansStartWithoutPayment(): void
    {
        Http::fake();
        $free = Plan::factory()->free()->create();

        $this->postJson($this->url('/subscribe'), ['plan' => $free->ulid])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('checkout_url', null);

        $this->assertSame($free->id, $this->workspace->plan()->id);
        Http::assertNothingSent();
    }

    public function testPaidPlansStartAfterFlutterwaveConfirmsThePayment(): void
    {
        $this->fakeFlutterwave();

        $reference = $this->checkout();

        Http::assertSent(fn ($request) => $request->url() === 'https://api.flutterwave.com/v3/payments'
            && $request['tx_ref'] === $reference
            && $request['amount'] == 5000
            && $request['currency'] === 'NGN'
            && $request['redirect_url'] === 'https://app.test/billing'
            && $request->hasHeader('Authorization', 'Bearer FLWSECK_TEST-x'));

        $this->assertSame('free', $this->workspace->plan()->slug);

        $this->postJson($this->url('/verify'), ['reference' => $reference])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.plan.slug', 'starter');

        $this->assertSame('complete', Transaction::firstWhere('reference', $reference)->status);
        $this->assertSame('starter', $this->workspace->fresh()->plan()->slug);
        $this->assertTrue($this->workspace->fresh()->currentSubscription->ends_at->isSameDay(now()->addMonth()));
    }

    public function testUnderpaidOrWrongCurrencyPaymentsAreRejected(): void
    {
        $this->fakeFlutterwave(paid: 50);
        $reference = $this->checkout();

        $this->postJson($this->url('/verify'), ['reference' => $reference])->assertUnprocessable();

        $this->assertSame('rejected', Transaction::firstWhere('reference', $reference)->status);
        $this->assertSame('free', $this->workspace->fresh()->plan()->slug);
    }

    public function testAnotherWorkspacesPaymentCannotBeVerifiedHere(): void
    {
        $this->fakeFlutterwave();
        $reference = $this->checkout();

        $other = Workspace::factory()->create();
        Sanctum::actingAs($other->owner);

        $this->postJson("/api/workspaces/{$other->ulid}/billing/verify", ['reference' => $reference])->assertNotFound();
    }

    public function testWebhooksActivateSubscriptionsOnceAndNeedTheSecretHash(): void
    {
        $this->fakeFlutterwave();
        $reference = $this->checkout();
        $payload = ['event' => 'charge.completed', 'data' => ['tx_ref' => $reference, 'status' => 'successful']];

        $this->postJson('/api/webhooks/flutterwave', $payload, ['verif-hash' => 'wrong'])->assertUnauthorized();

        $this->postJson('/api/webhooks/flutterwave', $payload, ['verif-hash' => 'hook-secret'])->assertNoContent();
        $this->postJson('/api/webhooks/flutterwave', $payload, ['verif-hash' => 'hook-secret'])->assertNoContent();

        $this->assertSame('starter', $this->workspace->fresh()->plan()->slug);
        $this->assertSame(1, $this->workspace->subscriptions()->where('status', 'active')->count());
        Http::assertSentCount(2);
    }

    public function testRenewingExtendsAndChangingPlanReplaces(): void
    {
        $this->fakeFlutterwave();

        $this->postJson($this->url('/verify'), ['reference' => $this->checkout()])->assertOk();
        $first = $this->workspace->fresh()->currentSubscription;

        $this->postJson($this->url('/verify'), ['reference' => $this->checkout()])->assertOk();
        $renewal = $this->workspace->fresh()->currentSubscription;

        $this->assertTrue($renewal->starts_at->equalTo($first->ends_at));
        $this->assertTrue($renewal->ends_at->isSameDay(now()->addMonths(2)));

        $free = Plan::factory()->free()->create();
        $this->postJson($this->url('/subscribe'), ['plan' => $free->ulid])->assertCreated();

        $this->assertSame($free->id, $this->workspace->fresh()->plan()->id);
        $this->assertSame(2, $this->workspace->subscriptions()->where('status', 'expired')->count());
    }

    public function testCancelledPlansLastUntilThePeriodEnds(): void
    {
        $this->fakeFlutterwave();
        $this->postJson($this->url('/verify'), ['reference' => $this->checkout()])->assertOk();

        $this->postJson($this->url('/cancel'))->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson($this->url('/cancel'))->assertConflict();

        $this->assertSame('starter', $this->workspace->fresh()->plan()->slug);

        $this->travelTo(now()->addMonth()->addMinute());
        $this->artisan('billing:expire-subscriptions')->assertSuccessful();

        $this->assertSame('free', $this->workspace->fresh()->plan()->slug);
        $this->assertSame(SubscriptionStatus::EXPIRED, Subscription::query()->where('plan_id', $this->starter->id)->value('status'));
    }

    public function testOnlyOwnersAndAdminsManageBilling(): void
    {
        $member = User::factory()->create();
        $this->workspace->members()->attach($member, ['role' => 'member']);
        Sanctum::actingAs($member);

        $this->getJson($this->url(''))->assertOk();
        $this->postJson($this->url('/subscribe'), ['plan' => $this->starter->ulid])->assertForbidden();
        $this->getJson($this->url('/transactions'))->assertForbidden();
    }

    public function testTransactionsAreListedForTheWorkspace(): void
    {
        $this->fakeFlutterwave();
        $reference = $this->checkout();

        $this->getJson($this->url('/transactions'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', $reference)
            ->assertJsonPath('data.0.method', 'flutterwave')
            ->assertJsonPath('data.0.due', 5000)
            ->assertJsonPath('data.0.subscription.currency', 'NGN')
            ->assertJsonPath('data.0.subscription.plan.name', $this->starter->name);
    }
}
