<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ProviderConnection;
use App\Models\Template;
use App\Models\Workspace;
use App\Services\Campaigns\OpenTracker;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OpenTrackingTest extends TestCase
{
    protected Workspace $workspace;

    protected ContactList $list;

    protected Template $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->list = ContactList::factory()->for($this->workspace)->create();
        $this->template = Template::factory()->email()->for($this->workspace)->create();

        Sanctum::actingAs($this->workspace->owner);
    }

    protected function mailgun(array $credentials = []): ProviderConnection
    {
        return ProviderConnection::factory()->for($this->workspace)
            ->provider('mailgun', 'email', ['domain' => 'mg.example.com', 'api_key' => 'k', ...$credentials], ['from_email' => 'news@example.com'])
            ->create(['is_default' => true]);
    }

    protected function launch(int $contacts = 1): Campaign
    {
        $this->list->addContacts(Contact::factory()->count($contacts)->for($this->workspace)->create()->modelKeys());

        $id = $this->postJson("/api/workspaces/{$this->workspace->ulid}/campaigns", [
            'name' => 'Launch',
            'channel' => 'email',
            'template' => $this->template->ulid,
            'lists' => [$this->list->ulid],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/workspaces/{$this->workspace->ulid}/campaigns/{$id}/launch")->assertAccepted();

        return Campaign::firstWhere('ulid', $id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function mailgunEvent(string $event, string $messageId, string $key = 'whsec'): array
    {
        $timestamp = (string) now()->timestamp;

        return [
            'signature' => ['timestamp' => $timestamp, 'token' => 'tok', 'signature' => hash_hmac('sha256', $timestamp . 'tok', $key)],
            'event-data' => ['event' => $event, 'timestamp' => now()->timestamp, 'message' => ['headers' => ['message-id' => $messageId]]],
        ];
    }

    public function testEmailsCarryAPixelThatRecordsOpens(): void
    {
        Http::fake(['api.mailgun.net/*' => Http::response(['id' => '<m1@mg>'])]);
        $this->mailgun();

        $campaign = $this->launch();
        $message = $campaign->messages()->first();
        $pixel = app(OpenTracker::class)->pixelUrl($message);

        Http::assertSent(fn ($request) => str_contains($request['html'], 'src="' . e($pixel) . '"')
            && ! isset($request['o:tracking-opens']));

        $this->get($pixel)->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->get($pixel)->assertOk();

        $message->refresh();
        $this->assertNotNull($message->opened_at);
        $this->assertSame(2, $message->opens_count);
        $this->assertSame('delivered', $message->status->value);

        $this->getJson("/api/workspaces/{$this->workspace->ulid}/campaigns/{$campaign->ulid}/report")
            ->assertJsonPath('data.opened', 1)
            ->assertJsonPath('data.open_rate', 100);
    }

    public function testTamperedPixelsStillLoadButRecordNothing(): void
    {
        Http::fake(['api.mailgun.net/*' => Http::response(['id' => '<m1@mg>'])]);
        $this->mailgun();

        [$message, $other] = $this->launch(2)->messages()->orderBy('id')->get()->all();
        $forged = str_replace("/open/{$message->id}?", "/open/{$other->id}?", app(OpenTracker::class)->pixelUrl($message));

        $this->get($forged)->assertOk()->assertHeader('Content-Type', 'image/gif');

        $this->assertSame(0, $other->refresh()->opens_count);
        $this->assertSame(0, $message->refresh()->opens_count);
    }

    public function testMailgunWithASigningKeyTracksOpensThroughItsWebhook(): void
    {
        Http::fake(['api.mailgun.net/*' => Http::response(['id' => '<m1@mg>'])]);
        $connection = $this->mailgun(['webhook_signing_key' => 'whsec']);

        $message = $this->launch()->messages()->first();

        Http::assertSent(fn ($request) => $request['o:tracking-opens'] === 'yes'
            && ! str_contains($request['html'], '/track/open/'));

        $this->postJson("/api/webhooks/status/{$connection->ulid}", $this->mailgunEvent('opened', 'm1@mg', 'wrong'))->assertNoContent();
        $this->assertNull($message->refresh()->opened_at);

        $this->postJson("/api/webhooks/status/{$connection->ulid}", $this->mailgunEvent('opened', 'm1@mg'))->assertNoContent();
        $this->assertNotNull($message->refresh()->opened_at);
        $this->assertSame(1, $message->opens_count);

        $this->postJson("/api/webhooks/status/{$connection->ulid}", $this->mailgunEvent('delivered', 'm1@mg'))->assertNoContent();
        $this->assertSame('delivered', $message->refresh()->status->value);
    }

    public function testOpenEventsCannotTouchOtherWorkspaces(): void
    {
        Http::fake(['api.mailgun.net/*' => Http::response(['id' => '<m1@mg>'])]);
        $this->mailgun(['webhook_signing_key' => 'whsec']);

        $message = $this->launch()->messages()->first();
        $stranger = ProviderConnection::factory()
            ->provider('mailgun', 'email', ['domain' => 'x.example.com', 'api_key' => 'k', 'webhook_signing_key' => 'whsec'])
            ->create();

        $this->postJson("/api/webhooks/status/{$stranger->ulid}", $this->mailgunEvent('opened', 'm1@mg'))->assertNoContent();

        $this->assertNull($message->refresh()->opened_at);
    }
}
