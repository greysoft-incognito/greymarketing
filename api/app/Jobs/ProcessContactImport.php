<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\ContactImport;
use App\Services\Audience\ContactImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessContactImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public ContactImport $import) {}

    public function handle(ContactImporter $importer): void
    {
        $importer->run($this->import);
    }

    public function failed(?Throwable $exception): void
    {
        $this->import->forceFill([
            'status' => ImportStatus::FAILED,
            'failures' => [['line' => 0, 'reason' => 'The import stopped unexpectedly.']],
        ])->save();
    }
}
