<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBillingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::first();

        $this->artisan('app:sync-roles');
        $admin->syncRoles(config('permission-defs.roles', []));
        $admin->syncPermissions(config('permission-defs.permissions', []));

        Sanctum::actingAs($admin);
    }

    public function testAdminsManagePlans(): void
    {
        $id = $this->postJson('/api/admin/plans', [
            'name' => 'Agency',
            'slug' => 'agency',
            'price' => 75000,
            'currency' => 'ngn',
            'interval' => 'year',
            'contacts_limit' => null,
            'emails_limit' => 1000000,
            'sms_limit' => 100000,
        ])->assertCreated()
            ->assertJsonPath('data.currency', 'NGN')
            ->assertJsonPath('data.limits.contacts', null)
            ->json('data.id');

        $this->putJson("/api/admin/plans/{$id}", ['is_default' => true, 'slug' => 'free'])
            ->assertUnprocessable()->assertJsonValidationErrors('slug');

        $this->putJson("/api/admin/plans/{$id}", ['is_default' => true])->assertOk();

        $this->assertSame('agency', Plan::fallback()->slug);
        $this->assertSame(1, Plan::query()->where('is_default', true)->count());

        $this->getJson('/api/admin/plans')->assertOk()->assertJsonCount(4, 'data');
        $this->deleteJson("/api/admin/plans/{$id}")->assertAccepted();
    }

    public function testPlansWithSubscriptionsCannotBeDeleted(): void
    {
        $workspace = Workspace::factory()->create();
        $starter = Plan::firstWhere('slug', 'starter');

        $this->postJson("/api/admin/workspaces/{$workspace->ulid}/subscription", ['plan' => $starter->ulid])->assertCreated();

        $this->deleteJson("/api/admin/plans/{$starter->ulid}")->assertConflict();
        $this->getJson('/api/admin/plans')->assertJsonPath('data.1.active_subscriptions_count', 1);
    }

    public function testAdminsGrantPlansAndSeeWorkspaceUsage(): void
    {
        $workspace = Workspace::factory()->create(['name' => 'Acme Stores']);
        Contact::factory()->count(2)->for($workspace)->create();
        $growth = Plan::firstWhere('slug', 'growth');

        $this->postJson("/api/admin/workspaces/{$workspace->ulid}/subscription", [
            'plan' => $growth->ulid,
            'ends_at' => now()->addDays(14)->toIso8601String(),
        ])->assertCreated()->assertJsonPath('data.amount', 0);

        $this->getJson("/api/admin/workspaces/{$workspace->ulid}")
            ->assertOk()
            ->assertJsonPath('data.plan.slug', 'growth')
            ->assertJsonPath('data.usage.contacts.used', 2);

        $this->getJson('/api/admin/workspaces?search=acme')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.contacts_count', 2)
            ->assertJsonPath('data.0.subscription.status', 'active');

        $this->getJson("/api/admin/workspaces?plan={$growth->ulid}")->assertJsonCount(1, 'data');

        $subscription = Subscription::firstWhere('workspace_id', $workspace->id);

        $this->getJson('/api/admin/subscriptions?status=active')->assertOk()->assertJsonPath('data.0.workspace.name', 'Acme Stores');
        $this->postJson("/api/admin/subscriptions/{$subscription->ulid}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->getJson('/api/admin/transactions')->assertOk();
    }

    public function testRegularUsersCannotUseTheAdminBillingApi(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/plans')->assertForbidden();
        $this->getJson('/api/admin/workspaces')->assertForbidden();
        $this->getJson('/api/admin/transactions')->assertForbidden();
    }
}
