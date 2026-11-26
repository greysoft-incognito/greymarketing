<?php

namespace App\Messaging\Data;

use App\Enums\Engagement;
use Illuminate\Support\Carbon;

final readonly class EngagementReport
{
    public function __construct(
        public string $messageId,
        public Engagement $type,
        public ?Carbon $at = null,
    ) {}
}
