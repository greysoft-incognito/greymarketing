<?php

namespace Tests\Feature;

use App\Enums\Channel;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceStatsTest extends TestCase
{
    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();

        Sanctum::actingAs($this->workspace->owner);
    }

    /**
     * @param  array<int, string>  $statuses
     */
    protected function sent(string $channel, array $statuses, mixed $at): void
    {
        $campaign = Campaign::factory()->for($this->workspace)->create(['channel' => $channel]);

        foreach ($statuses as $status) {
            $contact = Contact::factory()->for($this->workspace)->create();

            $campaign->messages()->forceCreate([
                'contact_id' => $contact->id,
                'to' => $contact->phone ?? $contact->email,
                'status' => $status,
                'sent_at' => $status === 'failed' ? null : $at,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }

    public function testStatsSummariseSendingAndAudience(): void
    {
        $this->travelTo(now()->setDay(15)->setTime(12, 0));

        $this->sent('sms', ['delivered', 'delivered', 'sent', 'failed'], now()->subDay());
        $this->sent('email', ['delivered'], now());
        $this->sent('sms', ['delivered'], now()->subMonth());
        Contact::factory()->for($this->workspace)->create()->unsubscribe(Channel::SMS);

        $response = $this->getJson("/api/workspaces/{$this->workspace->ulid}/stats?days=7")->assertOk();

        $daily = collect($response->json('data.daily'));

        $this->assertCount(7, $daily);
        $this->assertSame(['date' => now()->subDay()->toDateString(), 'email' => 0, 'sms' => 3], $daily[5]);
        $this->assertSame(['date' => now()->toDateString(), 'email' => 1, 'sms' => 0], $daily[6]);

        $response->assertJsonPath('data.this_month', ['sent' => 4, 'delivered' => 3, 'failed' => 1, 'delivery_rate' => 75])
            ->assertJsonPath('data.last_month.sent', 1)
            ->assertJsonPath('data.contacts.total', 7)
            ->assertJsonPath('data.contacts.added_this_month', 7)
            ->assertJsonPath('data.contacts.opt_outs_this_month', 1);
    }

    public function testStatsAreOnlyForMembers(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/workspaces/{$this->workspace->ulid}/stats")->assertForbidden();
    }
}
