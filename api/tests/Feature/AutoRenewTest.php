<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Helpers\Provider;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Notifications\RenewalFailed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AutoRenewTest extends TestCase
{
    protected Workspace $workspace;

    protected Plan $starter;

    protected function setUp(): void
    {
        parent::setUp();

        Provider::config(['flutterwave_secret_key' => 'FLWSECK_TEST-x']);

        $this->workspace = Workspace::factory()->create();
        $this->starter = Plan::firstWhere('slug', 'starter');

        Sanctum::actingAs($this->workspace->owner);
    }

    protected function url(string $path): string
    {
        return "/api/workspaces/{$this->workspace->ulid}/billing{$path}";
    }

    protected function fakeFlutterwave(string $charge = 'successful', bool $card = true): void
    {
        Http::fake([
            'api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'success',
                'data' => ['link' => 'https://checkout.flutterwave.com/v3/hosted/pay/abc'],
            ]),
            'api.flutterwave.com/v3/tokenized-charges' => Http::response([
                'status' => $charge === 'successful' ? 'success' : 'error',
                'message' => $charge === 'successful' ? 'Charge successful' : 'Insufficient funds',
                'data' => ['status' => $charge],
            ], $charge === 'successful' ? 200 : 400),
            'api.flutterwave.com/v3/transactions/verify_by_reference*' => Http::response([
                'status' => 'success',
                'data' => [
                    'status' => 'successful',
                    'amount' => 5000,
                    'currency' => 'NGN',
                    'customer' => ['email' => 'payer@example.com'],
                    'card' => $card ? [
                        'token' => 'flw-t1nf-secret',
                        'type' => 'VISA',
                        'last_4digits' => '4242',
                        'expiry' => '09/32',
                        'country' => 'NG',
                    ] : null,
                ],
            ]),
        ]);
    }

    /**
     * Pay for Starter through checkout and return the active subscription.
     */
    protected function subscribe(): Subscription
    {
        $this->postJson($this->url('/subscribe'), ['plan' => $this->starter->ulid])->assertCreated();

        $reference = Transaction::query()->latest('id')->value('reference');

        $this->postJson($this->url('/verify'), ['reference' => $reference])->assertOk();

        return $this->workspace->fresh()->currentSubscription;
    }

    public function testCardPaymentsTurnOnAutoRenewalWithoutExposingTheToken(): void
    {
        $this->fakeFlutterwave();
        $subscription = $this->subscribe();

        $this->assertTrue($subscription->auto_renew);
        $this->assertSame('flw-t1nf-secret', $subscription->card['token']);
        $this->assertSame('payer@example.com', $subscription->card['email']);

        $this->getJson($this->url(''))
            ->assertOk()
            ->assertJsonPath('data.subscription.auto_renew', true)
            ->assertJsonPath('data.subscription.card.last4', '4242')
            ->assertJsonPath('data.subscription.card.brand', 'VISA')
            ->assertJsonMissingPath('data.subscription.card.token')
            ->assertDontSee('flw-t1nf-secret');
    }

    public function testDueSubscriptionsAreChargedAndExtended(): void
    {
        $this->fakeFlutterwave();
        $first = $this->subscribe();

        $this->travelTo($first->ends_at->copy()->subHours(12));
        $this->artisan('billing:renew-subscriptions')->assertSuccessful();

        Http::assertSent(fn ($request) => $request->url() === 'https://api.flutterwave.com/v3/tokenized-charges'
            && $request['token'] === 'flw-t1nf-secret'
            && $request['email'] === 'payer@example.com'
            && $request['amount'] == 5000
            && $request['currency'] === 'NGN');

        $renewal = $this->workspace->fresh()->currentSubscription;

        $this->assertNotSame($first->id, $renewal->id);
        $this->assertTrue($renewal->starts_at->equalTo($first->ends_at));
        $this->assertTrue($renewal->auto_renew);
        $this->assertSame(SubscriptionStatus::EXPIRED, $first->fresh()->status);
        $this->assertSame(2, Transaction::query()->where('status', 'complete')->count());

        $this->artisan('billing:renew-subscriptions')->assertSuccessful();
        Http::assertSentCount(4);
    }

    public function testSubscriptionsAreNotChargedEarlyOrWhenRenewalIsOff(): void
    {
        $this->fakeFlutterwave();
        $subscription = $this->subscribe();

        $this->artisan('billing:renew-subscriptions')->assertSuccessful();

        $this->putJson($this->url('/auto-renew'), ['enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.auto_renew', false)
            ->assertJsonPath('data.renews_at', null);

        $this->travelTo($subscription->ends_at->copy()->subHours(2));
        $this->artisan('billing:renew-subscriptions')->assertSuccessful();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'tokenized-charges'));
    }

    public function testFailedChargesAreRetriedThenTheSubscriberIsNotified(): void
    {
        Notification::fake();
        $this->fakeFlutterwave(charge: 'failed');
        $subscription = $this->subscribe();
        $start = $subscription->ends_at->copy()->subHours(20);

        foreach ([0, 3, 6, 9, 12] as $hours) {
            $this->travelTo($start->copy()->addHours($hours));
            $this->artisan('billing:renew-subscriptions')->assertSuccessful();
        }

        Http::assertSentCount(2 + Subscription::RENEWAL_ATTEMPTS);
        $this->assertSame(Subscription::RENEWAL_ATTEMPTS, $subscription->fresh()->renewal_attempts);
        $this->assertSame($subscription->id, $this->workspace->fresh()->currentSubscription->id);
        $this->assertSame(Subscription::RENEWAL_ATTEMPTS, Transaction::query()->where('status', 'rejected')->count());

        Notification::assertSentToTimes($this->workspace->owner, RenewalFailed::class, 1);
    }

    public function testAutoRenewalNeedsASavedCard(): void
    {
        $this->fakeFlutterwave(card: false);
        $subscription = $this->subscribe();

        $this->assertFalse($subscription->auto_renew);
        $this->putJson($this->url('/auto-renew'), ['enabled' => true])->assertUnprocessable();
    }

    public function testTurningAutoRenewalOnUndoesACancellation(): void
    {
        $this->fakeFlutterwave();
        $this->subscribe();

        $this->postJson($this->url('/cancel'))->assertOk()->assertJsonPath('data.auto_renew', false);

        $this->putJson($this->url('/auto-renew'), ['enabled' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.auto_renew', true)
            ->assertJsonPath('data.cancelled_at', null);
    }
}
