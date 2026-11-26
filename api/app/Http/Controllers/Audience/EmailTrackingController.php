<?php

namespace App\Http\Controllers\Audience;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Models\CampaignMessage;
use App\Services\Campaigns\EmailTracker;
use Illuminate\Http\Request;

/**
 * Serves the open pixel and click redirects in campaign emails.
 */
class EmailTrackingController extends Controller
{
    /**
     * Always returns the pixel so a bad or stale link never shows as a broken image.
     */
    public function open(Request $request, int $message, EmailTracker $tracker)
    {
        $found = $request->hasValidSignature() ? CampaignMessage::find($message) : null;

        if ($found) {
            $tracker->recordOpen($found);
        }

        return response(base64_decode(EmailTracker::PIXEL), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Only signed links redirect, so this cannot be used to send people to other sites.
     */
    public function click(Request $request, int $message, EmailTracker $tracker)
    {
        $url = (string) $request->query('url');

        abort_unless(
            $request->hasValidSignature() && preg_match('/^https?:\/\//i', $url),
            HttpStatus::NOT_FOUND->value,
            'Link Does Not Exist'
        );

        $found = CampaignMessage::find($message);

        if ($found) {
            $tracker->recordClick($found);
        }

        return redirect()->away($url);
    }
}
