<?php

namespace App\Messaging\Data;

final readonly class EmailMessage
{
    public function __construct(
        public string $to,
        public string $subject,
        public ?string $html = null,
        public ?string $text = null,
        public ?string $toName = null,
        public ?string $fromEmail = null,
        public ?string $fromName = null,
    ) {}
}
