<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Template;
use App\Models\Workspace;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TemplateTest extends TestCase
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
        return "/api/workspaces/{$this->workspace->ulid}/templates{$path}";
    }

    public function testSmsTemplateReportsTagsAndSegments(): void
    {
        $this->postJson($this->url(), [
            'channel' => 'sms',
            'name' => 'Promo',
            'body' => 'Hi {{ first_name | there }}, ' . str_repeat('x', 150),
        ])
            ->assertCreated()
            ->assertJsonPath('data.tags', ['first_name'])
            ->assertJsonPath('data.sms.encoding', 'gsm7')
            ->assertJsonPath('data.sms.segments', 2);
    }

    public function testChannelRulesAreEnforced(): void
    {
        $this->postJson($this->url(), ['channel' => 'sms', 'name' => 'Bad', 'body' => 'Hi', 'subject' => 'Nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subject');

        $this->postJson($this->url(), ['channel' => 'email', 'name' => 'Bad', 'body' => 'Hi'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subject');

        $this->postJson($this->url(), ['channel' => 'email', 'name' => 'Bad', 'subject' => 'Hi'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('html');
    }

    public function testChannelCannotChangeOnUpdate(): void
    {
        $template = Template::factory()->for($this->workspace)->create();

        $this->patchJson($this->url("/{$template->ulid}"), ['channel' => 'email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('channel');

        $this->patchJson($this->url("/{$template->ulid}"), ['body' => 'New {{ city }}'])
            ->assertAccepted()
            ->assertJsonPath('data.tags', ['city']);
    }

    public function testPreviewRendersForAContactAndEscapesHtml(): void
    {
        $template = Template::factory()->email()->for($this->workspace)->create();
        $contact = Contact::factory()->for($this->workspace)->create(['first_name' => '<Ada>']);

        $response = $this->postJson($this->url("/{$template->ulid}/preview"), ['contact' => $contact->ulid])
            ->assertOk()
            ->assertJsonPath('data.subject', 'Hello <Ada>');

        $html = $response->json('data.html');

        $this->assertStringContainsString('Hi &lt;Ada&gt;', $html);
        $this->assertStringContainsString('/unsubscribe/' . $contact->ulid, $html);
    }

    public function testPreviewFallsBackToSampleData(): void
    {
        $template = Template::factory()->for($this->workspace)->create();

        $this->postJson($this->url("/{$template->ulid}/preview"))
            ->assertJsonPath('data.body', 'Hi there, our sale starts today.');

        $this->postJson($this->url("/{$template->ulid}/preview"), ['data' => ['first_name' => 'Obi']])
            ->assertJsonPath('data.body', 'Hi Obi, our sale starts today.')
            ->assertJsonPath('data.sms.segments', 1);
    }

    public function testTemplatesCanBeFilteredByChannel(): void
    {
        Template::factory()->for($this->workspace)->create();
        Template::factory()->email()->for($this->workspace)->create();

        $this->getJson($this->url('?channel=email'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.channel', 'email');
    }
}
