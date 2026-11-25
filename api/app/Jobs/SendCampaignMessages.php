<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Services\Campaigns\CampaignSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCampaignMessages implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    /**
     * @param  array<int, int>  $messageIds
     */
    public function __construct(
        public Campaign $campaign,
        public array $messageIds,
    ) {}

    public function handle(CampaignSender $sender): void
    {
        [$retry, $delay] = $sender->send($this->campaign, $this->messageIds);

        if ($retry) {
            static::dispatch($this->campaign, $retry)->delay(now()->addSeconds($delay));
        }
    }
}
