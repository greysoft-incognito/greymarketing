<?php

namespace App\Services\Campaigns;

use App\Enums\Channel;
use App\Enums\Engagement;
use App\Enums\MessageStatus;
use App\Messaging\Data\EngagementReport;
use App\Models\CampaignMessage;
use App\Models\ProviderConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Tracks opens and link clicks in campaign emails, from our own pixel and links or from provider events.
 */
class EmailTracker
{
    /**
     * A transparent 1x1 GIF.
     */
    public const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /**
     * Add the open pixel and send every web link through the click redirect, except the excluded ones.
     *
     * @param  array<int, string>  $except  Links to leave as they are, such as the unsubscribe link.
     */
    public function track(CampaignMessage $message, string $html, array $except = []): string
    {
        $html = preg_replace_callback(
            '/(<a\b[^>]*?\bhref\s*=\s*)(["\'])(https?:\/\/[^"\']+)\2/i',
            function (array $match) use ($message, $except) {
                $url = html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5);

                if (in_array($url, $except, true)) {
                    return $match[0];
                }

                return $match[1] . $match[2] . e($this->clickUrl($message, $url)) . $match[2];
            },
            $html
        );

        return $this->withPixel($html, $this->pixelUrl($message));
    }

    public function pixelUrl(CampaignMessage $message): string
    {
        return URL::signedRoute('track.open', ['message' => $message->id]);
    }

    public function clickUrl(CampaignMessage $message, string $url): string
    {
        return URL::signedRoute('track.click', ['message' => $message->id, 'url' => $url]);
    }

    /**
     * Add the pixel at the end of the body, or of the document when there is no body tag.
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
    public function recordOpen(CampaignMessage $message, ?Carbon $at = null): bool
    {
        if (! $this->trackable($message)) {
            return false;
        }

        $at ??= now();

        $message->increment('opens_count', 1, $this->firstSeen($message, $at));

        return true;
    }

    /**
     * Count a click. A click also counts as an open, since images may have been blocked.
     */
    public function recordClick(CampaignMessage $message, ?Carbon $at = null): bool
    {
        if (! $this->trackable($message)) {
            return false;
        }

        $at ??= now();

        $message->increment('clicks_count', 1, [
            ...$this->firstSeen($message, $at),
            'clicked_at' => $message->clicked_at ?? $at,
            'opens_count' => $message->opens_count ?: 1,
        ]);

        return true;
    }

    public function recordReport(ProviderConnection $connection, EngagementReport $report): bool
    {
        $message = CampaignMessage::query()
            ->where('provider_message_id', $report->messageId)
            ->whereHas('campaign', fn ($query) => $query
                ->where('workspace_id', $connection->workspace_id)
                ->where('channel', Channel::EMAIL))
            ->first();

        if (! $message) {
            return false;
        }

        return $report->type === Engagement::CLICK
            ? $this->recordClick($message, $report->at)
            : $this->recordOpen($message, $report->at);
    }

    protected function trackable(CampaignMessage $message): bool
    {
        return in_array($message->status, [MessageStatus::SENT, MessageStatus::DELIVERED], true);
    }

    /**
     * Columns set by the first open or click.
     *
     * @return array<string, mixed>
     */
    protected function firstSeen(CampaignMessage $message, Carbon $at): array
    {
        return array_filter([
            'opened_at' => $message->opened_at ? null : $at,
            'status' => $message->status === MessageStatus::SENT ? MessageStatus::DELIVERED : null,
            'delivered_at' => $message->delivered_at ? null : $at,
        ]);
    }
}
