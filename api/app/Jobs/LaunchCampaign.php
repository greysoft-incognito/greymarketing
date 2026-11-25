<?php

namespace App\Jobs;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Services\Campaigns\CampaignLauncher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class LaunchCampaign implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public Campaign $campaign) {}

    public function handle(CampaignLauncher $launcher): void
    {
        $launcher->launch($this->campaign);
    }

    public function failed(?Throwable $exception): void
    {
        $this->campaign->forceFill(['status' => CampaignStatus::FAILED])->save();
    }
}
