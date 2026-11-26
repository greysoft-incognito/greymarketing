<?php

namespace Tests\Feature;

use App\Enums\Channel;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ProviderConnection;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportTest extends TestCase
{
    protected Workspace $workspace;

    protected ProviderConnection $termii;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->termii = ProviderConnection::factory()->for($this->workspace)->create();

        Sanctum::actingAs($this->workspace->owner);
    }

    /**
     * @param  array<int, string|array{0: string, 1: string}>  $messages  Statuses, or [status, error] pairs.
     */
    protected function campaign(string $channel, array $messages, mixed $startedAt, ?ProviderConnection $connection = null): Campaign
    {
        $campaign = Campaign::factory()->for($this->workspace)->create(['channel' => $channel]);

        $campaign->forceFill([
            'status' => 'completed',
            'provider_connection_id' => $connection?->id,
            'recipients_count' => count($messages),
            'started_at' => $startedAt,
        ])->save();

        foreach ($messages as $message) {
            [$status, $error] = is_array($message) ? $message : [$message, null];
            $contact = Contact::factory()->for($this->workspace)->create();

            $campaign->messages()->forceCreate([
                'contact_id' => $contact->id,
                'to' => $contact->phone ?? $contact->email,
                'status' => $status,
                'error' => $error,
                'sent_at' => $status === 'failed' ? null : $startedAt,
            ]);
        }

        return $campaign;
    }

    protected function url(string $path = ''): string
    {
        return "/api/workspaces/{$this->workspace->ulid}/reports{$path}";
    }

    public function testReportSummarisesCampaignsStartedInThePeriod(): void
    {
        $this->campaign('sms', ['delivered', 'delivered', 'sent', ['failed', 'DND'], ['failed', 'DND'], ['failed', 'Invalid number'], 'skipped'], now()->subDays(2), $this->termii);
        $this->campaign('email', ['delivered'], now());
        $this->campaign('sms', ['delivered'], now()->subDays(40), $this->termii);
        Contact::factory()->for($this->workspace)->create()->unsubscribe(Channel::SMS);

        $response = $this->getJson($this->url('?days=30'))->assertOk();

        $response->assertJsonPath('data.campaigns', 2)
            ->assertJsonPath('data.counts', ['pending' => 0, 'sent' => 1, 'delivered' => 3, 'failed' => 3, 'skipped' => 1])
            ->assertJsonPath('data.delivery_rate', 75)
            ->assertJsonPath('data.failure_rate', 42.9)
            ->assertJsonPath('data.opt_outs', 1)
            ->assertJsonPath('data.failure_reasons', [['reason' => 'DND', 'total' => 2], ['reason' => 'Invalid number', 'total' => 1]])
            ->assertJsonCount(30, 'data.daily');

        $connections = collect($response->json('data.connections'));

        $this->assertSame(
            ['id' => $this->termii->ulid, 'name' => 'Termii', 'provider' => 'termii', 'channel' => 'sms', 'sent' => 3, 'delivered' => 2, 'failed' => 3, 'delivery_rate' => 66.7],
            $connections->first()
        );
        $this->assertNull($connections->last()['id']);
    }

    public function testReportFiltersByChannel(): void
    {
        $this->campaign('sms', ['delivered', 'failed'], now());
        $this->campaign('email', ['delivered'], now());

        $this->getJson($this->url('?channel=email'))
            ->assertOk()
            ->assertJsonPath('data.channel', 'email')
            ->assertJsonPath('data.campaigns', 1)
            ->assertJsonPath('data.counts.delivered', 1)
            ->assertJsonPath('data.counts.failed', 0);
    }

    public function testCampaignsAreListedWithTheirDeliveryCounts(): void
    {
        $this->campaign('sms', ['delivered'], now()->subDays(40));
        $older = $this->campaign('sms', ['delivered', 'sent', 'failed'], now()->subDay(), $this->termii);
        $newer = $this->campaign('email', ['delivered'], now());
        Campaign::factory()->for($this->workspace)->create();

        $this->getJson($this->url('/campaigns?limit=10'))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $newer->ulid)
            ->assertJsonPath('data.1.id', $older->ulid)
            ->assertJsonPath('data.1.connection.id', $this->termii->ulid)
            ->assertJsonPath('data.1.counts', ['sent' => 2, 'delivered' => 1, 'failed' => 1])
            ->assertJsonPath('data.1.delivery_rate', 50);
    }

    public function testReportValidatesItsFilters(): void
    {
        $this->getJson($this->url('?days=365&channel=fax'))->assertUnprocessable()->assertJsonValidationErrors(['days', 'channel']);
    }

    public function testReportsAreOnlyForMembers(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson($this->url())->assertForbidden();
        $this->getJson($this->url('/campaigns'))->assertForbidden();
    }
}
