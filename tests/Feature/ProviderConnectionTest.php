<?php

namespace Tests\Feature;

use App\Models\ProviderConnection;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProviderConnectionTest extends TestCase
{
    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();

        Sanctum::actingAs($this->workspace->owner);
    }

    protected function url(string $path = ''): string
    {
        return "/api/workspaces/{$this->workspace->ulid}/connections{$path}";
    }

    public function testCatalogListsProvidersWithoutDriverClasses(): void
    {
        $response = $this->getJson('/api/providers')->assertOk();

        $keys = collect($response->json('data'))->pluck('key');

        $this->assertEqualsCanonicalizing(
            ['africastalking', 'sendpulse', 'termii', 'twilio', 'mailgun', 'smtp'],
            $keys->all()
        );
        $this->assertStringNotContainsString('Driver', $response->getContent());
    }

    public function testCredentialsAreEncryptedAndMaskedInResponses(): void
    {
        $this->postJson($this->url(), [
            'provider' => 'africastalking',
            'channel' => 'sms',
            'name' => 'AT main',
            'credentials' => ['username' => 'grey', 'api_key' => 'atsk_super_secret_9876'],
            'settings' => ['sender_id' => 'GREY'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.credentials.username', 'grey')
            ->assertJsonPath('data.credentials.api_key', '••••••9876')
            ->assertJsonPath('data.is_default', true);

        $raw = DB::table('provider_connections')->value('credentials');

        $this->assertStringNotContainsString('atsk_super_secret_9876', $raw);
        $this->assertSame('atsk_super_secret_9876', ProviderConnection::first()->credentials['api_key']);
    }

    public function testProviderMustSupportTheChannel(): void
    {
        $this->postJson($this->url(), [
            'provider' => 'mailgun',
            'channel' => 'sms',
            'name' => 'Wrong',
            'credentials' => ['domain' => 'mg.example.com', 'api_key' => 'key'],
        ])->assertUnprocessable()->assertJsonValidationErrors('channel');
    }

    public function testRequiredCredentialsAreValidated(): void
    {
        $this->postJson($this->url(), [
            'provider' => 'twilio',
            'channel' => 'sms',
            'name' => 'Twilio',
            'credentials' => ['account_sid' => 'AC123'],
        ])->assertUnprocessable()->assertJsonValidationErrors('credentials.auth_token');
    }

    public function testUpdatingKeepsUnchangedSecretsAndResetsStatus(): void
    {
        $connection = ProviderConnection::factory()->for($this->workspace)->create();
        $connection->markTested();

        $this->patchJson($this->url("/{$connection->ulid}"), [
            'settings' => ['sender_id' => 'NEWID'],
        ])->assertAccepted()->assertJsonPath('data.status', 'active');

        $this->patchJson($this->url("/{$connection->ulid}"), [
            'credentials' => ['api_key' => 'tm_rotated_5555'],
        ])->assertAccepted()->assertJsonPath('data.status', 'untested');

        $connection->refresh();

        $this->assertSame('tm_rotated_5555', $connection->credentials['api_key']);
        $this->assertSame('NEWID', $connection->settings['sender_id']);
    }

    public function testOnlyOneDefaultPerChannel(): void
    {
        $first = ProviderConnection::factory()->for($this->workspace)->create(['is_default' => true]);
        $second = ProviderConnection::factory()->for($this->workspace)->create();

        $this->patchJson($this->url("/{$second->ulid}"), ['is_default' => true])->assertAccepted();

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function testConnectionsOfAnotherWorkspaceAreNotReachable(): void
    {
        $other = ProviderConnection::factory()->create();

        $this->getJson($this->url("/{$other->ulid}"))->assertNotFound();
    }

    public function testMembersCanViewButNotManageConnections(): void
    {
        $connection = ProviderConnection::factory()->for($this->workspace)->create();
        $member = User::factory()->create();
        $this->workspace->members()->attach($member, ['role' => 'member']);

        Sanctum::actingAs($member);

        $this->getJson($this->url())->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson($this->url("/{$connection->ulid}"))->assertForbidden();
        $this->postJson($this->url("/{$connection->ulid}/test"))->assertForbidden();
    }

    public function testTestEndpointRecordsTheOutcome(): void
    {
        $connection = ProviderConnection::factory()->for($this->workspace)->create();

        Http::fake(['api.ng.termii.com/*' => Http::sequence()
            ->push(['balance' => 10])
            ->push(['message' => 'Invalid API key'], 401)]);

        $this->postJson($this->url("/{$connection->ulid}/test"))
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->postJson($this->url("/{$connection->ulid}/test"))
            ->assertUnprocessable()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('message', 'Termii: Invalid API key');
    }

    public function testSendTestMessage(): void
    {
        $connection = ProviderConnection::factory()->for($this->workspace)->create();

        Http::fake(['api.ng.termii.com/*' => Http::response(['message_id' => 'tm-1', 'message' => 'Successfully Sent'])]);

        $this->postJson($this->url("/{$connection->ulid}/send-test"), [
            'to' => '+2348012345678',
            'body' => 'Hello from GreyMarketing',
        ])->assertOk()->assertJsonPath('data.message_id', 'tm-1');

        $this->postJson($this->url("/{$connection->ulid}/send-test"), [
            'to' => 'not-a-number',
            'body' => 'Hi',
        ])->assertUnprocessable()->assertJsonValidationErrors('to');
    }
}
