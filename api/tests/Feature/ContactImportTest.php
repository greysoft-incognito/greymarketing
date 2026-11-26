<?php

namespace Tests\Feature;

use App\Jobs\ProcessContactImport;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\ContactList;
use App\Models\Plan;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContactImportTest extends TestCase
{
    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->workspace = Workspace::factory()->create();

        Sanctum::actingAs($this->workspace->owner);
    }

    protected function url(string $path = ''): string
    {
        return "/api/workspaces/{$this->workspace->ulid}/imports{$path}";
    }

    protected function upload(string $csv): string
    {
        return $this->post($this->url(), [
            'file' => UploadedFile::fake()->createWithContent('contacts.csv', $csv),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
    }

    public function testUploadReturnsHeadersSampleAndSuggestedMapping(): void
    {
        $this->post($this->url(), [
            'file' => UploadedFile::fake()->createWithContent('c.csv', "\xEF\xBB\xBFE-mail,Mobile Number,First Name,City\nada@example.com,08012345678,Ada,Lagos\n"),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('headers', ['E-mail', 'Mobile Number', 'First Name', 'City'])
            ->assertJsonPath('sample.0.City', 'Lagos')
            ->assertJsonPath('data.mapping', [
                'E-mail' => 'email',
                'Mobile Number' => 'phone',
                'First Name' => 'first_name',
                'City' => 'fields.city',
            ])
            ->assertJsonPath('data.status', 'pending');
    }

    public function testImportCreatesUpdatesAndSkipsRows(): void
    {
        $list = ContactList::factory()->for($this->workspace)->create();
        $existing = Contact::factory()->for($this->workspace)->create([
            'email' => 'ada@example.com',
            'phone' => null,
            'first_name' => 'Old',
        ]);

        $id = $this->upload(implode("\n", [
            'email,phone,first_name,city',
            'ADA@example.com,08012345678,Ada,Lagos',
            'obi@example.com,,Obi,Abuja',
            ',0803 111 2222,Chi,',
            'not-an-email,123,Bad,',
            'obi@example.com,,Obi again,',
        ]));

        $this->postJson($this->url("/{$id}/start"), [
            'mapping' => ['email' => 'email', 'phone' => 'phone', 'first_name' => 'first_name', 'city' => 'fields.city'],
            'list' => $list->ulid,
        ])->assertAccepted();

        $import = ContactImport::firstWhere('ulid', $id);

        $this->assertSame('completed', $import->status->value);
        $this->assertSame(5, $import->processed);
        $this->assertSame(3, $import->imported);
        $this->assertSame(2, $import->skipped);
        $this->assertSame([5, 6], array_column($import->failures, 'line'));

        $existing->refresh();
        $this->assertSame('Ada', $existing->first_name);
        $this->assertSame('+2348012345678', $existing->phone);
        $this->assertSame('Lagos', $existing->fields['city']);

        $this->assertSame(3, $this->workspace->contacts()->count());
        $this->assertSame(3, $list->contacts()->count());
        Storage::disk('local')->assertMissing($import->file_path);
    }

    public function testMappingNeedsEmailOrPhone(): void
    {
        Queue::fake();

        $id = $this->upload("name,city\nAda,Lagos\n");

        $this->postJson($this->url("/{$id}/start"), ['mapping' => ['name' => 'first_name', 'city' => 'fields.city']])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Map at least one column to email or phone.');

        $this->postJson($this->url("/{$id}/start"), ['mapping' => ['name' => 'drop table', 'city' => 'phone']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mapping.name');

        Queue::assertNothingPushed();
    }

    public function testAnImportCanOnlyStartOnce(): void
    {
        Queue::fake();

        $id = $this->upload("email\nada@example.com\n");

        $this->postJson($this->url("/{$id}/start"), ['mapping' => ['email' => 'email']])->assertAccepted();
        ContactImport::where('ulid', $id)->update(['status' => 'processing']);
        $this->postJson($this->url("/{$id}/start"), ['mapping' => ['email' => 'email']])->assertStatus(409);

        Queue::assertPushed(ProcessContactImport::class, 1);
    }

    public function testLargeFilesAreImportedInChunks(): void
    {
        Plan::fallback()->update(['contacts_limit' => null]);

        $rows = ['email'];

        for ($i = 0; $i < 1203; $i++) {
            $rows[] = "user{$i}@example.com";
        }

        $id = $this->upload(implode("\n", $rows));

        $this->postJson($this->url("/{$id}/start"), ['mapping' => ['email' => 'email']])->assertAccepted();

        $this->assertSame(1203, ContactImport::firstWhere('ulid', $id)->imported);
        $this->assertSame(1203, $this->workspace->contacts()->count());
    }

    public function testVcardFilesAreReadAsRows(): void
    {
        $vcf = implode("\r\n", [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'N:Okafor;Ada;;;',
            'FN:Ada Okafor',
            'EMAIL;TYPE=INTERNET:ada@example.com',
            'TEL;TYPE=HOME:0701 000 0000',
            'item1.TEL;TYPE=CELL:0801 234 5678',
            'ADR;TYPE=WORK:;;12 Allen Ave;Lagos;;;Nigeria',
            'NOTE:Met at the\, Lagos fair\nlikes chin',
            ' chin',
            'END:VCARD',
            'BEGIN:VCARD',
            'VERSION:2.1',
            'FN:Obi Nwosu',
            'TEL:+2348031112222',
            'END:VCARD',
        ]);

        $response = $this->post($this->url(), [
            'file' => $this->realFile('phone.vcf', $vcf),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('headers', ['First name', 'Last name', 'Email', 'Phone', 'City', 'Note'])
            ->assertJsonPath('sample.0.Phone', '0801 234 5678')
            ->assertJsonPath('sample.0.Note', "Met at the, Lagos fair\nlikes chinchin")
            ->assertJsonPath('sample.1.First name', 'Obi')
            ->assertJsonPath('sample.1.Last name', 'Nwosu')
            ->assertJsonPath('data.mapping', [
                'First name' => 'first_name',
                'Last name' => 'last_name',
                'Email' => 'email',
                'Phone' => 'phone',
                'City' => 'fields.city',
                'Note' => 'fields.note',
            ]);

        $id = $response->json('data.id');

        $this->postJson($this->url("/{$id}/start"), ['mapping' => $response->json('data.mapping')])->assertAccepted();

        $this->assertSame(2, ContactImport::firstWhere('ulid', $id)->imported);
        $this->assertSame('+2348012345678', $this->workspace->contacts()->firstWhere('email', 'ada@example.com')->phone);
        $this->assertSame('Lagos', $this->workspace->contacts()->firstWhere('email', 'ada@example.com')->fields['city']);
    }

    public function testOtherFileTypesAreRejected(): void
    {
        $this->post($this->url(), [
            'file' => UploadedFile::fake()->create('contacts.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    /**
     * An upload backed by a real file, so its type is sniffed from the content like a browser upload.
     */
    protected function realFile(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }
}
