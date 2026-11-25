<?php

namespace App\Messaging\Data;

final readonly class InboundSms
{
    public function __construct(
        public string $from,
        public string $body,
    ) {}
}
