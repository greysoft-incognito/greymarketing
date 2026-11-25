<?php

namespace App\Services\Campaigns;

use App\Enums\MessageStatus;
use App\Messaging\Data\DeliveryReport;
use App\Models\CampaignMessage;
use App\Models\ProviderConnection;

/**
 * Applies a provider's delivery outcome to the matching campaign message.
 */
class DeliveryReportRecorder
{
    public function record(ProviderConnection $connection, DeliveryReport $report): bool
    {
        $message = CampaignMessage::query()
            ->where('provider_message_id', $report->messageId)
            ->whereIn('status', [MessageStatus::SENT, MessageStatus::DELIVERED, MessageStatus::FAILED])
            ->whereHas('campaign', fn ($query) => $query->where('workspace_id', $connection->workspace_id))
            ->first();

        if (! $message) {
            return false;
        }

        $message->forceFill($report->delivered
            ? ['status' => MessageStatus::DELIVERED, 'delivered_at' => $message->delivered_at ?? now(), 'error' => null]
            : ['status' => MessageStatus::FAILED, 'error' => $report->error])->save();

        return true;
    }
}
