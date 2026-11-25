<?php

namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Jobs\LaunchCampaign;
use App\Models\Campaign;
use Illuminate\Console\Command;

class DispatchDueCampaigns extends Command
{
    protected $signature = 'campaigns:dispatch-due';

    protected $description = 'Launch scheduled campaigns whose send time has passed';

    public function handle(): int
    {
        Campaign::query()
            ->where('status', CampaignStatus::SCHEDULED)
            ->where('scheduled_at', '<=', now())
            ->chunkById(100, function ($campaigns) {
                $campaigns->each(fn (Campaign $campaign) => LaunchCampaign::dispatch($campaign));
            });

        return self::SUCCESS;
    }
}
