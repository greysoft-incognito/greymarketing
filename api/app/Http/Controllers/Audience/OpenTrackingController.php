<?php

namespace App\Http\Controllers\Audience;

use App\Http\Controllers\Controller;
use App\Models\CampaignMessage;
use App\Services\Campaigns\OpenTracker;
use Illuminate\Http\Request;

/**
 * Serves the tracking pixel in campaign emails and records the open.
 *
 * The pixel is always returned so a bad or stale link never shows as a broken image.
 */
class OpenTrackingController extends Controller
{
    public function __invoke(Request $request, int $message, OpenTracker $tracker)
    {
        $found = $request->hasValidSignature() ? CampaignMessage::find($message) : null;

        if ($found) {
            $tracker->record($found);
        }

        return response(base64_decode(OpenTracker::PIXEL), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
