<?php

namespace App\Services\Campaigns;

use App\Enums\Channel;
use App\Enums\MessageStatus;
use App\Messaging\Data\OpenReport;
use App\Models\CampaignMessage;
use App\Models\ProviderConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Records email opens from tracking pixels and provider open events.
 */
class OpenTracker
{
    /**
     * A transparent 1x1 GIF.
     */
    public const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function pixelUrl(CampaignMessage $message): string
    {
        return URL::signedRoute('track.open', ['message' => $message->id]);
    }

    /**
     * Add the tracking pixel at the end of the body, or of the document when there is no body tag.
     */
    public function withPixel(string $html, string $url): string
    {
        $pixel = '<img src="' . e($url) . '" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;" />';
        $position = strripos($html, '</body>');

        return $position === false ? $html . $pixel : substr_replace($html, $pixel, $position, 0);
    }

    /**
     * Count an open. The first one also proves delivery for providers that send no receipt.
     */
    public function record(CampaignMessage $message, ?Carbon $at = null): bool
    {
        if (! in_array($message->status, [MessageStatus::SENT, MessageStatus::DELIVERED], true)) {
            return false;
        }

        $at ??= now();

        $message->increment('opens_count', 1, array_filter([
            'opened_at' => $message->opened_at ? null : $at,
            'status' => $message->status === MessageStatus::SENT ? MessageStatus::DELIVERED : null,
            'delivered_at' => $message->delivered_at ? null : $at,
        ]));

        return true;
    }

    public function recordReport(ProviderConnection $connection, OpenReport $report): bool
    {
        $message = CampaignMessage::query()
            ->where('provider_message_id', $report->messageId)
            ->whereHas('campaign', fn ($query) => $query
                ->where('workspace_id', $connection->workspace_id)
                ->where('channel', Channel::EMAIL))
            ->first();

        return $message ? $this->record($message, $report->openedAt) : false;
    }
}
