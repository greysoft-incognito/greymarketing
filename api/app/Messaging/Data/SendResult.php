<?php

namespace App\Messaging\Data;

final readonly class SendResult
{
    /**
     * @param  array<string, mixed>  $raw  The provider's response body.
     */
    public function __construct(
        public ?string $messageId,
        public string $status = 'queued',
        public array $raw = [],
    ) {}
}
