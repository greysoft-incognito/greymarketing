<?php

namespace App\Services\Audience;

use App\Enums\ImportStatus;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Services\Billing\UsageLimits;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContactImporter
{
    public const CHUNK_SIZE = 500;

    /**
     * Fields a CSV column can be mapped to, besides custom `fields.<name>`.
     */
    public const FIELDS = ['email', 'phone', 'first_name', 'last_name'];

    protected const SYNONYMS = [
        'email' => ['email', 'e mail', 'email address', 'mail'],
        'phone' => ['phone', 'phone number', 'mobile', 'mobile number', 'msisdn', 'telephone', 'tel', 'whatsapp'],
        'first_name' => ['first name', 'firstname', 'first_name', 'given name', 'name'],
        'last_name' => ['last name', 'lastname', 'last_name', 'surname', 'family name'],
    ];

    public function __construct(
        protected ContactNormalizer $normalizer,
        protected UsageLimits $limits,
    ) {}

    /**
     * Guess a mapping from CSV headers to contact fields.
     *
     * @param  array<int, string>  $headers
     * @return array<string, string|null>
     */
    public function suggestMapping(array $headers): array
    {
        $taken = [];

        return collect($headers)->mapWithKeys(function (string $header) use (&$taken) {
            $key = Str::of($header)->lower()->replace(['_', '-'], ' ')->squish()->toString();

            foreach (self::SYNONYMS as $field => $names) {
                if (! in_array($field, $taken, true) && in_array($key, $names, true)) {
                    $taken[] = $field;

                    return [$header => $field];
                }
            }

            return [$header => $key === '' ? null : 'fields.' . Str::snake($key)];
        })->all();
    }

    public function reader(ContactImport $import): ContactFileReader
    {
        return ContactFileReader::for(Storage::path($import->file_path));
    }

    /**
     * Import every row, chunk by chunk, recording progress on the import.
     */
    public function run(ContactImport $import): void
    {
        $import->forceFill(['status' => ImportStatus::PROCESSING])->save();

        $failures = [];

        foreach ($this->reader($import)->chunks(self::CHUNK_SIZE) as $chunk) {
            [$rows, $chunkFailures] = $this->prepare($import, $chunk);

            [$ids, $limitFailures] = $this->store($import, $rows);
            $chunkFailures = [...$chunkFailures, ...$limitFailures];

            $import->contactList?->addContacts($ids);

            $failures = array_slice([...$failures, ...$chunkFailures], 0, ContactImport::MAX_FAILURES);

            $import->forceFill([
                'processed' => $import->processed + count($chunk),
                'imported' => $import->imported + count($ids),
                'skipped' => $import->skipped + count($chunkFailures),
                'failures' => $failures,
            ])->save();
        }

        $import->forceFill([
            'status' => ImportStatus::COMPLETED,
            'completed_at' => now(),
        ])->save();

        Storage::delete($import->file_path);
    }

    /**
     * Map and normalise a chunk, dropping invalid rows and duplicates within the chunk.
     *
     * @param  array<int, array<string, string>>  $chunk
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array{line: int, reason: string}>}
     */
    protected function prepare(ContactImport $import, array $chunk): array
    {
        $country = $import->workspace->default_country;
        $rows = [];
        $failures = [];
        $seen = [];

        foreach ($chunk as $line => $raw) {
            $row = ['fields' => []];

            foreach ($import->mapping as $header => $field) {
                $value = $raw[$header] ?? '';

                if (! $field || $value === '') {
                    continue;
                }

                if (str_starts_with($field, 'fields.')) {
                    $row['fields'][substr($field, 7)] = $value;
                } else {
                    $row[$field] = $value;
                }
            }

            $email = $this->normalizer->email($row['email'] ?? null);
            $phone = $this->normalizer->phone($row['phone'] ?? null, $country);

            if (! $email && ! $phone) {
                $failures[] = ['line' => $line, 'reason' => 'No valid email or phone number.'];

                continue;
            }

            $keys = array_filter(['e:' . $email => $email, 'p:' . $phone => $phone]);

            if (array_intersect_key($seen, $keys)) {
                $failures[] = ['line' => $line, 'reason' => 'Duplicate of an earlier row.'];

                continue;
            }

            $seen += $keys;

            $rows[] = [
                'line' => $line,
                'email' => $email,
                'phone' => $phone,
                'first_name' => isset($row['first_name']) ? Str::limit($row['first_name'], 100, '') : null,
                'last_name' => isset($row['last_name']) ? Str::limit($row['last_name'], 100, '') : null,
                'fields' => $row['fields'],
            ];
        }

        return [$rows, $failures];
    }

    /**
     * Update contacts that already exist and insert the rest, up to the plan's contact limit.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{0: array<int, int>, 1: array<int, array{line: int, reason: string}>} Stored contact ids and rows over the limit.
     */
    protected function store(ContactImport $import, array $rows): array
    {
        if (! $rows) {
            return [[], []];
        }

        $workspace = $import->workspace;
        $room = $this->limits->contactRoom($workspace);
        $failures = [];
        $emails = array_values(array_filter(array_column($rows, 'email')));
        $phones = array_values(array_filter(array_column($rows, 'phone')));

        $existing = $workspace->contacts()
            ->where(fn ($query) => $query->whereIn('email', $emails)->orWhereIn('phone', $phones))
            ->get();

        $byEmail = $existing->whereNotNull('email')->keyBy('email');
        $byPhone = $existing->whereNotNull('phone')->keyBy('phone');

        $ids = [];
        $inserts = [];
        $now = now();

        foreach ($rows as $row) {
            /** @var Contact|null $contact */
            $contact = $byEmail->get($row['email']) ?? $byPhone->get($row['phone']);

            if ($contact) {
                $contact->fill(array_filter([
                    'email' => $contact->email ? null : $row['email'],
                    'phone' => $contact->phone || $byPhone->has($row['phone']) ? null : $row['phone'],
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                ]));

                if ($row['fields']) {
                    $contact->fields = [...($contact->fields ?? []), ...$row['fields']];
                }

                $contact->save();
                $ids[] = $contact->id;

                continue;
            }

            if ($room !== null && count($inserts) >= $room) {
                $failures[] = ['line' => $row['line'], 'reason' => "Your plan's contact limit has been reached."];

                continue;
            }

            unset($row['line']);

            $inserts[] = [
                ...$row,
                'ulid' => (string) Str::ulid(),
                'workspace_id' => $workspace->id,
                'fields' => $row['fields'] ? json_encode($row['fields']) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($inserts) {
            Contact::query()->insert($inserts);

            $ids = [...$ids, ...$workspace->contacts()->whereIn('ulid', array_column($inserts, 'ulid'))->pluck('id')->all()];
        }

        return [$ids, $failures];
    }
}
