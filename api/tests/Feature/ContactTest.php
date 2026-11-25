<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactList;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContactTest extends TestCase
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
        return "/api/workspaces/{$this->workspace->ulid}{$path}";
    }

    public function testCreatingAContactNormalisesEmailAndPhone(): void
    {
        $list = ContactList::factory()->for($this->workspace)->create();

        $this->postJson($this->url('/contacts'), [
            'email' => ' Ada@Example.COM ',
            'phone' => '0801 234 5678',
            'first_name' => 'Ada',
            'fields' => ['city' => 'Lagos'],
            'lists' => [$list->ulid],
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'ada@example.com')
            ->assertJsonPath('data.phone', '+2348012345678')
            ->assertJsonPath('data.fields.city', 'Lagos')
            ->assertJsonPath('data.lists.0.id', $list->ulid);
    }

    public function testContactsAreUniquePerWorkspace(): void
    {
        Contact::factory()->for($this->workspace)->create(['phone' => '+2348012345678']);
        Contact::factory()->create(['email' => 'shared@example.com']);

        $this->postJson($this->url('/contacts'), ['phone' => '08012345678'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->postJson($this->url('/contacts'), ['email' => 'shared@example.com'])->assertCreated();
    }

    public function testInvalidPhoneIsRejected(): void
    {
        $this->postJson($this->url('/contacts'), ['phone' => '12345'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function testEmailOrPhoneIsRequired(): void
    {
        $this->postJson($this->url('/contacts'), ['first_name' => 'Ada'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone']);
    }

    public function testContactsCanBeFiltered(): void
    {
        $list = ContactList::factory()->for($this->workspace)->create();
        $ada = Contact::factory()->for($this->workspace)->create(['first_name' => 'Ada']);
        $obi = Contact::factory()->for($this->workspace)->create(['first_name' => 'Obi']);
        $list->addContacts([$ada->id]);
        $obi->unsubscribe(\App\Enums\Channel::SMS);

        $this->getJson($this->url('/contacts?search=ada'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ada->ulid);
        $this->getJson($this->url("/contacts?list={$list->ulid}"))->assertJsonCount(1, 'data');
        $this->getJson($this->url('/contacts?subscribed=sms'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ada->ulid);
    }

    public function testSubscriptionCanBeToggled(): void
    {
        $contact = Contact::factory()->for($this->workspace)->create();

        $this->patchJson($this->url("/contacts/{$contact->ulid}"), ['email_subscribed' => false])
            ->assertAccepted()
            ->assertJsonPath('data.email_subscribed', false)
            ->assertJsonPath('data.sms_subscribed', true);
    }

    public function testContactsOfOtherWorkspacesAreHidden(): void
    {
        $other = Contact::factory()->create();

        $this->getJson($this->url("/contacts/{$other->ulid}"))->assertNotFound();
    }

    public function testMembersCanManageContactsButOutsidersCannot(): void
    {
        $member = User::factory()->create();
        $this->workspace->members()->attach($member, ['role' => 'member']);

        Sanctum::actingAs($member);
        $this->postJson($this->url('/contacts'), ['email' => 'a@b.co'])->assertCreated();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson($this->url('/contacts'), ['email' => 'c@d.co'])->assertForbidden();
    }

    public function testListsCountAndManageMembers(): void
    {
        $contacts = Contact::factory()->count(3)->for($this->workspace)->create();
        $foreign = Contact::factory()->create();

        $list = $this->postJson($this->url('/lists'), ['name' => 'VIP'])->assertCreated()->json('data.id');

        $this->postJson($this->url("/lists/{$list}/contacts"), [
            'contacts' => [...$contacts->pluck('ulid'), $foreign->ulid],
        ])->assertAccepted()->assertJsonPath('data.contacts_count', 3);

        $this->postJson($this->url("/lists/{$list}/contacts"), ['contacts' => [$contacts[0]->ulid]])
            ->assertJsonPath('data.contacts_count', 3);

        $this->deleteJson($this->url("/lists/{$list}/contacts"), ['contacts' => [$contacts[0]->ulid]])
            ->assertJsonPath('data.contacts_count', 2);

        $this->getJson($this->url('/lists'))->assertJsonPath('data.0.contacts_count', 2);
        $this->assertModelExists($contacts[0]);
    }
}
