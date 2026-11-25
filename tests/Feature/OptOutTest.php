<?php

namespace Tests\Feature;

use App\Enums\Channel;
use App\Helpers\Provider;
use App\Models\Contact;
use App\Models\ProviderConnection;
use App\Models\Workspace;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OptOutTest extends TestCase
{
    public function testUnsubscribeLinkOnlyActsOnPost(): void
    {
        $contact = Contact::factory()->create();
        $url = $contact->unsubscribeUrl(Channel::EMAIL);

        $this->getJson($url)->assertOk()->assertJsonPath('data.email_subscribed', true);

        $this->postJson($url)->assertOk()->assertJsonPath('data.email_subscribed', false);

        $this->assertFalse($contact->fresh()->isSubscribedTo(Channel::EMAIL));
        $this->assertTrue($contact->fresh()->isSubscribedTo(Channel::SMS));
    }

    public function testUnsubscribeLinkRedirectsToTheConfiguredPage(): void
    {
        $contact = Contact::factory()->create();
        $url = $contact->unsubscribeUrl(Channel::SMS);

        Provider::config(['unsubscribe_page_url' => 'https://app.example.com/unsubscribe']);

        $this->get($url)->assertRedirect('https://app.example.com/unsubscribe?' . http_build_query(['action' => $url]));
    }

    public function testTamperedLinksAreRejected(): void
    {
        $contact = Contact::factory()->create();
        $other = Contact::factory()->create();

        $url = str_replace($contact->ulid, $other->ulid, $contact->unsubscribeUrl());

        $this->postJson($url)->assertForbidden();
        $this->assertTrue($other->fresh()->isSubscribedTo(Channel::EMAIL));
    }

    public function testAfricasTalkingStopAndStartReplies(): void
    {
        $workspace = Workspace::factory()->create();
        $contact = Contact::factory()->for($workspace)->create(['phone' => '+2348012345678']);
        $connection = ProviderConnection::factory()->for($workspace)
            ->provider('africastalking', 'sms', ['username' => 'grey', 'api_key' => 'k'])
            ->create();

        $this->post("/api/webhooks/inbound/{$connection->ulid}", ['from' => '+2348012345678', 'text' => ' stop please'])
            ->assertNoContent();
        $this->assertFalse($contact->fresh()->isSubscribedTo(Channel::SMS));

        $this->post("/api/webhooks/inbound/{$connection->ulid}", ['from' => '+2348012345678', 'text' => 'START'])
            ->assertNoContent();
        $this->assertTrue($contact->fresh()->isSubscribedTo(Channel::SMS));
    }

    public function testStopRepliesDoNotCrossWorkspaces(): void
    {
        $contact = Contact::factory()->create(['phone' => '+2348012345678']);
        $connection = ProviderConnection::factory()
            ->provider('africastalking', 'sms', ['username' => 'grey', 'api_key' => 'k'])
            ->create();

        $this->post("/api/webhooks/inbound/{$connection->ulid}", ['from' => '+2348012345678', 'text' => 'STOP'])
            ->assertNoContent();

        $this->assertTrue($contact->fresh()->isSubscribedTo(Channel::SMS));
    }

    public function testTwilioRepliesNeedAValidSignature(): void
    {
        $workspace = Workspace::factory()->create();
        $contact = Contact::factory()->for($workspace)->create(['phone' => '+2348012345678']);
        $connection = ProviderConnection::factory()->for($workspace)
            ->provider('twilio', 'sms', ['account_sid' => 'AC1', 'auth_token' => 'secret'])
            ->create();

        $url = url("/api/webhooks/inbound/{$connection->ulid}");
        $params = ['Body' => 'STOP', 'From' => '+2348012345678'];

        $this->post($url, $params, ['X-Twilio-Signature' => 'forged'])->assertStatus(400);
        $this->assertTrue($contact->fresh()->isSubscribedTo(Channel::SMS));

        $signature = base64_encode(hash_hmac('sha1', $url . 'BodySTOPFrom+2348012345678', 'secret', true));

        $this->post($url, $params, ['X-Twilio-Signature' => $signature])->assertNoContent();
        $this->assertFalse($contact->fresh()->isSubscribedTo(Channel::SMS));
    }

    public function testInboundUrlIsShownForProvidersThatSupportIt(): void
    {
        $connection = ProviderConnection::factory()
            ->provider('africastalking', 'sms', ['username' => 'grey', 'api_key' => 'k'])
            ->create();

        Sanctum::actingAs($connection->workspace->owner);

        $this->getJson("/api/workspaces/{$connection->workspace->ulid}/connections/{$connection->ulid}")
            ->assertJsonPath('data.inbound_url', route('webhooks.inbound', $connection->ulid));
    }
}
