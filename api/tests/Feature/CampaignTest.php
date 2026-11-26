<?php

namespace Tests\Feature;

use App\Enums\Channel;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ProviderConnection;
use App\Models\Template;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CampaignTest extends TestCase
{
    protected Workspace $workspace;

    protected ProviderConnection $connection;

    protected ContactList $list;

    protected Template $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->connection = ProviderConnection::factory()->for($this->workspace)->create(['is_default' => true]);
        $this->list = ContactList::factory()->for($this->workspace)->create();
        $this->template = Template::factory()->for($this->workspace)->create();

        Sanctum::actingAs($this->workspace->owner);
    }

    protected function url(string $path = ''): string
    {
        return "/api/workspaces/{$this->workspace->ulid}/campaigns{$path}";
    }

    protected function draft(array $overrides = []): string
    {
        return $this->postJson($this->url(), [
            'name' => 'Black Friday',
            'channel' => 'sms',
            'template' => $this->template->ulid,
            'lists' => [$this->list->ulid],
            ...$overrides,
        ])->assertCreated()->json('data.id');
    }

    /**
     * @return \Illuminate\Support\Collection<int, Contact>
     */
    protected function contacts(int $count, ?ContactList $list = null)
    {
        $contacts = Contact::factory()->count($count)->for($this->workspace)->create();
        ($list ?? $this->list)->addContacts($contacts->modelKeys());

        return $contacts;
    }

    public function testTemplateAndConnectionMustMatchTheChannel(): void
    {
        $email = Template::factory()->email()->for($this->workspace)->create();
        $foreign = Template::factory()->create();

        $this->postJson($this->url(), ['name' => 'X', 'channel' => 'sms', 'template' => $email->ulid])
            ->assertUnprocessable()->assertJsonValidationErrors('template');

        $this->postJson($this->url(), ['name' => 'X', 'channel' => 'sms', 'template' => $foreign->ulid])
            ->assertUnprocessable()->assertJsonValidationErrors('template');
    }

    public function testLaunchSendsToSubscribedContactsOnceAcrossLists(): void
    {
        Http::fake(['api.ng.termii.com/*' => Http::response(['message_id' => 'tm-1'])]);

        $contacts = $this->contacts(3);
        $second = ContactList::factory()->for($this->workspace)->create();
        $second->addContacts([$contacts[0]->id]);
        $contacts[1]->unsubscribe(Channel::SMS);
        Contact::factory()->for($this->workspace)->create(['phone' => null])->lists()->attach($this->list);

        $id = $this->draft(['lists' => [$this->list->ulid, $second->ulid]]);

        $this->getJson($this->url("/{$id}"))->assertOk()->assertJsonPath('audience', 2);

        $this->postJson($this->url("/{$id}/launch"))->assertAccepted();

        $this->getJson($this->url("/{$id}"))->assertOk()->assertJsonPath('audience', null);

        $campaign = Campaign::firstWhere('ulid', $id);

        $this->assertSame('completed', $campaign->status->value);
        $this->assertSame(2, $campaign->recipients_count);
        $this->assertSame($this->connection->id, $campaign->provider_connection_id);
        $this->assertSame(['pending' => 0, 'sent' => 2, 'delivered' => 0, 'failed' => 0, 'skipped' => 0], $campaign->statusCounts());

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['sms'] === "Hi {$contacts[0]->first_name}, our sale starts today.");
    }

    public function testTemplateChangesAfterLaunchDoNotAffectTheCampaign(): void
    {
        Queue::fake();

        $id = $this->draft();
        $this->contacts(1);

        app(\App\Services\Campaigns\CampaignLauncher::class)->launch(Campaign::firstWhere('ulid', $id));
        $this->template->update(['body' => 'Changed']);

        $this->assertSame('Hi {{ first_name | there }}, our sale starts today.', Campaign::firstWhere('ulid', $id)->body);
    }

    public function testLaunchNeedsTemplateListsAndConnection(): void
    {
        $id = $this->postJson($this->url(), ['name' => 'Empty', 'channel' => 'email'])->json('data.id');

        $this->postJson($this->url("/{$id}/launch"))->assertUnprocessable()->assertJsonPath('message', 'Choose a template first.');

        $template = Template::factory()->email()->for($this->workspace)->create();
        $this->patchJson($this->url("/{$id}"), ['template' => $template->ulid, 'lists' => [$this->list->ulid]])->assertAccepted();

        $this->postJson($this->url("/{$id}/launch"))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Connect a email provider or choose a connection first.');
    }

    public function testScheduledCampaignsLaunchWhenDue(): void
    {
        Http::fake(['*' => Http::response(['message_id' => 'tm-1'])]);
        $this->contacts(2);

        $id = $this->draft();

        $this->postJson($this->url("/{$id}/launch"), ['scheduled_at' => now()->addHour()->toIso8601String()])
            ->assertAccepted()
            ->assertJsonPath('data.status', 'scheduled');

        $this->artisan('campaigns:dispatch-due');
        $this->assertSame('scheduled', Campaign::firstWhere('ulid', $id)->status->value);

        $this->travel(61)->minutes();
        $this->artisan('campaigns:dispatch-due');

        $this->assertSame('completed', Campaign::firstWhere('ulid', $id)->status->value);
        Http::assertSentCount(2);
    }

    public function testCancellingSkipsPendingMessages(): void
    {
        Queue::fake();
        $this->contacts(3);

        $id = $this->draft();
        app(\App\Services\Campaigns\CampaignLauncher::class)->launch(Campaign::firstWhere('ulid', $id));

        $this->postJson($this->url("/{$id}/cancel"))->assertAccepted()->assertJsonPath('data.status', 'cancelled');

        $this->getJson($this->url("/{$id}/report"))
            ->assertOk()
            ->assertJsonPath('data.counts.skipped', 3)
            ->assertJsonPath('data.counts.pending', 0);

        $this->patchJson($this->url("/{$id}"), ['name' => 'Again'])->assertStatus(409);
    }

    public function testRateLimitedMessagesAreRetriedLater(): void
    {
        config(['messaging.default_rate_per_second' => 2]);
        Http::fake(['*' => Http::response(['message_id' => 'tm-1'])]);
        Queue::fake();
        $this->contacts(5);

        $id = $this->draft();
        $campaign = Campaign::firstWhere('ulid', $id);
        app(\App\Services\Campaigns\CampaignLauncher::class)->launch($campaign);

        [$retry, $delay] = app(\App\Services\Campaigns\CampaignSender::class)
            ->send($campaign, $campaign->messages()->pluck('id')->all());

        $this->assertCount(3, $retry);
        $this->assertGreaterThanOrEqual(1, $delay);
        $this->assertSame(2, $campaign->statusCounts()['sent']);
        $this->assertSame('sending', $campaign->fresh()->status->value);
    }

    public function testProviderOutagesAreRetriedThenFailed(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Service unavailable'], 503)]);
        Queue::fake();
        $this->contacts(1);

        $id = $this->draft();
        $campaign = Campaign::firstWhere('ulid', $id);
        app(\App\Services\Campaigns\CampaignLauncher::class)->launch($campaign);
        $sender = app(\App\Services\Campaigns\CampaignSender::class);
        $ids = $campaign->messages()->pluck('id')->all();

        $this->assertCount(1, $sender->send($campaign, $ids)[0]);
        $this->assertCount(1, $sender->send($campaign, $ids)[0]);
        $this->assertCount(0, $sender->send($campaign, $ids)[0]);

        $message = $campaign->messages()->first();
        $this->assertSame('failed', $message->status->value);
        $this->assertSame(3, $message->attempts);
        $this->assertSame('completed', $campaign->fresh()->status->value);
    }

    public function testRejectedMessagesFailWithoutRetry(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Invalid sender ID'], 400)]);
        $this->contacts(1);

        $id = $this->draft();
        $this->postJson($this->url("/{$id}/launch"))->assertAccepted();

        $this->getJson($this->url("/{$id}/messages?status=failed"))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.error', 'Termii: Invalid sender ID')
            ->assertJsonPath('data.0.attempts', 1);
    }

    public function testEmailCampaignsCarryUnsubscribeHeaders(): void
    {
        Http::fake(['api.mailgun.net/*' => Http::response(['id' => '<m1@mg>'])]);

        $connection = ProviderConnection::factory()->for($this->workspace)
            ->provider('mailgun', 'email', ['domain' => 'mg.example.com', 'api_key' => 'k'], ['from_email' => 'news@example.com'])
            ->create(['is_default' => true]);
        $contact = $this->contacts(1)->first();
        $template = Template::factory()->email()->for($this->workspace)->create();

        $id = $this->draft(['channel' => 'email', 'template' => $template->ulid]);
        $this->postJson($this->url("/{$id}/launch"))->assertAccepted();

        Http::assertSent(fn ($request) => str_contains($request['h:List-Unsubscribe'] ?? '', "/unsubscribe/{$contact->ulid}")
            && $request['h:List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click'
            && str_contains($request['html'], 'Hi ' . e($contact->first_name)));

        $this->assertSame('m1@mg', $connection->workspace->campaigns()->first()->messages()->value('provider_message_id'));
    }

    public function testDeliveryReportsUpdateMessagesAndTheReport(): void
    {
        Http::fake(['*' => Http::sequence()->push(['message_id' => 'tm-1'])->push(['message_id' => 'tm-2'])]);
        $contacts = $this->contacts(2);

        $id = $this->draft();
        $this->postJson($this->url("/{$id}/launch"))->assertAccepted();

        $this->postJson("/api/webhooks/status/{$this->connection->ulid}", ['message_id' => 'tm-1', 'status' => 'Delivered'])->assertNoContent();
        $this->postJson("/api/webhooks/status/{$this->connection->ulid}", ['message_id' => 'tm-2', 'status' => 'DND Active on Phone Number'])->assertNoContent();
        $contacts[0]->unsubscribe(Channel::SMS);

        $this->getJson($this->url("/{$id}/report"))
            ->assertJsonPath('data.counts.delivered', 1)
            ->assertJsonPath('data.counts.failed', 1)
            ->assertJsonPath('data.delivery_rate', 100)
            ->assertJsonPath('data.failure_rate', 50)
            ->assertJsonPath('data.opt_outs', 1);
    }

    public function testDeliveryReportsCannotTouchOtherWorkspaces(): void
    {
        Http::fake(['*' => Http::response(['message_id' => 'tm-1'])]);
        $this->contacts(1);

        $id = $this->draft();
        $this->postJson($this->url("/{$id}/launch"))->assertAccepted();

        $stranger = ProviderConnection::factory()->create();

        $this->postJson("/api/webhooks/status/{$stranger->ulid}", ['message_id' => 'tm-1', 'status' => 'Delivered'])->assertNoContent();

        $this->assertSame(1, Campaign::firstWhere('ulid', $id)->statusCounts()['sent']);
    }
}
