<?php

namespace App\Messaging\Data;

final readonly class DeliveryReport
{
    public function __construct(
        public string $messageId,
        public bool $delivered,
        public ?string $error = null,
    ) {}
}
