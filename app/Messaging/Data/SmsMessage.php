<?php

namespace App\Messaging\Data;

final readonly class SmsMessage
{
    public function __construct(
        public string $to,
        public string $body,
        public ?string $from = null,
    ) {}
}
