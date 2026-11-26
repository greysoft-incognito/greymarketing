<?php

namespace App\Messaging\Data;

use Illuminate\Support\Carbon;

final readonly class OpenReport
{
    public function __construct(
        public string $messageId,
        public ?Carbon $openedAt = null,
    ) {}
}
