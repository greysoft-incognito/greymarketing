<?php

namespace App\Services\Campaigns;

use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\MessageStatus;
use App\Jobs\SendCampaignMessages;
use App\Models\Campaign;
use Illuminate\Support\Facades\DB;

/**
 * Turns a campaign's audience into pending messages and queues them for sending.
 */
class CampaignLauncher
{
    public const BATCH_SIZE = 100;

    public function launch(Campaign $campaign): void
    {
        $claimed = Campaign::query()
            ->whereKey($campaign->id)
            ->whereIn('status', [CampaignStatus::DRAFT, CampaignStatus::SCHEDULED])
            ->update(['status' => CampaignStatus::PREPARING]);

        if (! $claimed) {
            return;
        }

        $campaign->refresh();

        DB::transaction(function () use ($campaign) {
            $campaign->snapshotTemplate();
            $this->createMessages($campaign);

            $campaign->forceFill([
                'recipients_count' => $campaign->messages()->count(),
                'provider_connection_id' => $campaign->sendingConnection()?->id,
                'status' => CampaignStatus::SENDING,
                'started_at' => now(),
            ])->save();
        });

        $this->queuePending($campaign);

        $campaign->completeIfDone();
    }

    /**
     * Queue every pending message in fixed-size batches.
     */
    public function queuePending(Campaign $campaign): void
    {
        $campaign->messages()
            ->where('status', MessageStatus::PENDING)
            ->select('id')
            ->chunkById(self::BATCH_SIZE, function ($messages) use ($campaign) {
                SendCampaignMessages::dispatch($campaign, $messages->modelKeys());
            });
    }

    /**
     * Insert one pending message per audience contact with a single INSERT ... SELECT.
     */
    protected function createMessages(Campaign $campaign): void
    {
        $address = $campaign->channel === Channel::EMAIL ? 'contacts.email' : 'contacts.phone';

        $select = $campaign->audience()
            ->toBase()
            ->selectRaw((int) $campaign->id . ' as campaign_id')
            ->addSelect('contacts.id as contact_id')
            ->addSelect("{$address} as to")
            ->selectRaw("'" . MessageStatus::PENDING->value . "' as status")
            ->selectRaw('CURRENT_TIMESTAMP as created_at')
            ->selectRaw('CURRENT_TIMESTAMP as updated_at');

        DB::table('campaign_messages')->insertUsing(
            ['campaign_id', 'contact_id', 'to', 'status', 'created_at', 'updated_at'],
            $select
        );
    }
}
