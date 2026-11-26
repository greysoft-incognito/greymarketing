<?php

namespace App\Messaging\Data;

final readonly class EmailMessage
{
    /**
     * @param  array<string, string>  $headers  Extra headers, such as List-Unsubscribe.
     * @param  bool  $trackEngagement  Ask the provider to track opens and clicks itself.
     */
    public function __construct(
        public string $to,
        public string $subject,
        public ?string $html = null,
        public ?string $text = null,
        public ?string $toName = null,
        public ?string $fromEmail = null,
        public ?string $fromName = null,
        public array $headers = [],
        public bool $trackEngagement = false,
    ) {}
}
