<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Plan;
use App\Models\ProviderConnection;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUsage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStatsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::first();

        $this->artisan('app:sync-roles');
        $admin->syncRoles(config('permission-defs.roles', []));
        $admin->syncPermissions(config('permission-defs.permissions', []));

        Sanctum::actingAs($admin);

        $this->travelTo(now()->setDay(15)->setTime(12, 0));
    }

    protected function subscribe(Workspace $workspace, string $slug, float $amount): void
    {
        $plan = Plan::firstWhere('slug', $slug);

        $subscription = $workspace->subscriptions()->make(['amount' => $amount, 'currency' => 'NGN']);
        $subscription->plan()->associate($plan);
        $subscription->save();
        $subscription->activate();
    }

    protected function used(Workspace $workspace, int $emails, int $sms, ?string $period = null): void
    {
        WorkspaceUsage::query()->insert([
            'workspace_id' => $workspace->id,
            'period' => $period ?? WorkspaceUsage::currentPeriod(),
            'emails_sent' => $emails,
            'sms_sent' => $sms,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<int, string>  $statuses
     */
    protected function sent(ProviderConnection $connection, array $statuses): void
    {
        $campaign = Campaign::factory()->for($connection->workspace)->create(['channel' => $connection->channel]);
        $campaign->providerConnection()->associate($connection)->save();

        foreach ($statuses as $index => $status) {
            $campaign->messages()->forceCreate([
                'to' => "+23480000000{$index}",
                'status' => $status,
                'sent_at' => $status === 'failed' ? null : now()->subHour(),
                'updated_at' => now()->subHour(),
            ]);
        }
    }

    public function testOverviewSummarisesThePlatform(): void
    {
        $paying = Workspace::factory()->create(['name' => 'Kola Foods']);
        $free = Workspace::factory()->create(['name' => 'GreenFarm']);

        $this->subscribe($paying, 'growth', 20000);
        $this->used($paying, 1000, 400);
        $this->used($paying, 500, 100, now()->subMonth()->startOfMonth()->toDateString());
        $this->used($free, 10, 250);

        $termii = ProviderConnection::factory()->for($paying)->create();
        $this->sent($termii, ['delivered', 'delivered', 'sent', 'failed']);

        $response = $this->getJson('/api/admin/stats')->assertOk();

        $response->assertJsonPath('data.workspaces.paying', 1)
            ->assertJsonPath('data.mrr.0', ['currency' => 'NGN', 'amount' => 20000])
            ->assertJsonPath('data.messages.this_month', ['emails' => 1010, 'sms' => 650])
            ->assertJsonPath('data.messages.last_month', ['emails' => 500, 'sms' => 100])
            ->assertJsonPath('data.providers.0', ['provider' => 'termii', 'total' => 4, 'failed' => 1, 'error_rate' => 25])
            ->assertJsonPath('data.over_limit', 1)
            ->assertJsonPath('data.top.0.name', 'Kola Foods')
            ->assertJsonPath('data.top.0.plan', 'Growth')
            ->assertJsonPath('data.top.1.sms', ['used' => 250, 'limit' => 200]);

        $plans = collect($response->json('data.plans'))->keyBy('name');

        $this->assertSame(1, $plans['Growth']['workspaces']);
        $this->assertSame(Workspace::count() - 1, $plans[Plan::fallback()->name]['workspaces']);
    }

    public function testUsageCoversOneMonth(): void
    {
        $workspace = Workspace::factory()->create();
        $this->used($workspace, 300, 20);
        $this->used($workspace, 7, 7, now()->subMonth()->startOfMonth()->toDateString());

        $connection = ProviderConnection::factory()->for($workspace)->create();
        $this->sent($connection, ['delivered', 'sent']);

        $this->getJson('/api/admin/stats/usage')
            ->assertOk()
            ->assertJsonPath('data.totals', ['emails' => 300, 'sms' => 20])
            ->assertJsonPath('data.delivery.sms.delivery_rate', 50)
            ->assertJsonPath('data.providers.0', ['provider' => 'termii', 'channel' => 'sms', 'total' => 2])
            ->assertJsonPath('data.top.0.id', $workspace->ulid);

        $this->getJson('/api/admin/stats/usage?month=' . now()->subMonth()->format('Y-m'))
            ->assertOk()
            ->assertJsonPath('data.totals', ['emails' => 7, 'sms' => 7])
            ->assertJsonCount(0, 'data.providers');

        $this->getJson('/api/admin/stats/usage?month=nope')->assertUnprocessable();
    }

    public function testWorkspaceListShowsThisMonthsUsageAndSubscriptionsFilterByWorkspace(): void
    {
        $workspace = Workspace::factory()->create(['name' => 'Acme Stores']);
        $this->used($workspace, 12, 3);
        $this->subscribe($workspace, 'growth', 20000);
        $this->subscribe(Workspace::factory()->create(), 'growth', 20000);

        $this->getJson('/api/admin/workspaces?search=acme')
            ->assertOk()
            ->assertJsonPath('data.0.usage_this_month', ['emails' => 12, 'sms' => 3]);

        $this->getJson("/api/admin/subscriptions?workspace={$workspace->ulid}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.workspace.name', 'Acme Stores');
    }

    public function testRegularUsersCannotSeePlatformStats(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/stats')->assertForbidden();
        $this->getJson('/api/admin/stats/usage')->assertForbidden();
    }
}
