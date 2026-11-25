<?php

namespace Tests\Feature;

use App\Enums\Channel;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\ContactList;
use App\Models\Plan;
use App\Models\ProviderConnection;
use App\Models\Template;
use App\Models\Workspace;
use App\Services\Billing\UsageLimits;
use App\Services\Campaigns\CampaignLauncher;
use App\Services\Campaigns\CampaignSender;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanLimitTest extends TestCase
{
    protected Workspace $workspace;

    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::fallback();
        $this->plan->update(['contacts_limit' => 3, 'sms_limit' => 2, 'emails_limit' => null]);
        $this->workspace = Workspace::factory()->create();

        Sanctum::actingAs($this->workspace->owner);
    }

    protected function url(string $path): string
    {
        return "/api/workspaces/{$this->workspace->ulid}{$path}";
    }

    /**
     * An SMS campaign draft with the given number of recipients.
     */
    protected function campaign(int $recipients): Campaign
    {
        ProviderConnection::factory()->for($this->workspace)->create(['is_default' => true]);
        $list = ContactList::factory()->for($this->workspace)->create();
        $list->addContacts(Contact::factory()->count($recipients)->for($this->workspace)->create()->modelKeys());

        $id = $this->postJson($this->url('/campaigns'), [
            'name' => 'Promo',
            'channel' => 'sms',
            'template' => Template::factory()->for($this->workspace)->create()->ulid,
            'lists' => [$list->ulid],
        ])->assertCreated()->json('data.id');

        return Campaign::firstWhere('ulid', $id);
    }

    public function testContactsStopAtThePlanLimit(): void
    {
        Contact::factory()->count(3)->for($this->workspace)->create();

        $this->postJson($this->url('/contacts'), ['email' => 'new@example.com'])
            ->assertStatus(402)
            ->assertJsonPath('message', "Your plan's contact limit has been reached.");
    }

    public function testImportsStopAtThePlanLimitButStillUpdateExistingContacts(): void
    {
        Storage::fake('local');
        Contact::factory()->for($this->workspace)->create(['email' => 'old@example.com']);

        $id = $this->post($this->url('/imports'), [
            'file' => UploadedFile::fake()->createWithContent('c.csv', "email,first name\nold@example.com,Ola\na@example.com,A\nb@example.com,B\nc@example.com,C\n"),
        ])->assertCreated()->json('data.id');

        $this->postJson($this->url("/imports/{$id}/start"), ['mapping' => ['email' => 'email', 'first name' => 'first_name']])->assertAccepted();

        $import = ContactImport::firstWhere('ulid', $id);

        $this->assertSame(3, $import->imported);
        $this->assertSame(1, $import->skipped);
        $this->assertSame([['line' => 5, 'reason' => "Your plan's contact limit has been reached."]], $import->failures);
        $this->assertSame('Ola', $this->workspace->contacts()->firstWhere('email', 'old@example.com')->first_name);
        $this->assertSame(3, $this->workspace->contacts()->count());
    }

    public function testLaunchIsRefusedWhenTheAudienceExceedsTheMonthlyAllowance(): void
    {
        $campaign = $this->campaign(3);

        $this->postJson($this->url("/campaigns/{$campaign->ulid}/launch"))
            ->assertStatus(402)
            ->assertJsonPath('message', 'This campaign has 3 recipients but your plan has 2 sms messages left this month.');
    }

    public function testSendingStopsAtTheLimitAndRecordsUsage(): void
    {
        Http::fake(['*' => Http::response(['message_id' => 'tm-1'])]);
        Queue::fake();

        $campaign = $this->campaign(3);
        $this->plan->update(['contacts_limit' => null]);
        app(CampaignLauncher::class)->launch($campaign);
        app(UsageLimits::class)->recordSent($this->workspace, Channel::SMS, 1);

        app(CampaignSender::class)->send($campaign, $campaign->messages()->pluck('id')->all());

        $this->assertSame(1, $campaign->statusCounts()['sent']);
        $this->assertSame(2, $campaign->statusCounts()['failed']);
        $this->assertSame(2, app(UsageLimits::class)->sent($this->workspace, Channel::SMS));
        Http::assertSentCount(1);
    }

    public function testUsageIsCountedPerMonth(): void
    {
        $limits = app(UsageLimits::class);

        $this->travelTo(now()->subMonth());
        $limits->recordSent($this->workspace, Channel::SMS, 2);
        $this->travelBack();

        $this->assertSame(0, $limits->sent($this->workspace, Channel::SMS));
        $this->assertSame(2, $limits->messageRoom($this->workspace, Channel::SMS));
        $this->assertNull($limits->messageRoom($this->workspace, Channel::EMAIL));
    }

    public function testBillingShowsThePlanAndUsage(): void
    {
        Contact::factory()->count(2)->for($this->workspace)->create();
        app(UsageLimits::class)->recordSent($this->workspace, Channel::SMS, 1);

        $this->getJson($this->url('/billing'))
            ->assertOk()
            ->assertJsonPath('data.plan.slug', 'free')
            ->assertJsonPath('data.subscription', null)
            ->assertJsonPath('data.usage.contacts', ['used' => 2, 'limit' => 3])
            ->assertJsonPath('data.usage.sms', ['used' => 1, 'limit' => 2])
            ->assertJsonPath('data.usage.emails', ['used' => 0, 'limit' => null]);
    }
}
