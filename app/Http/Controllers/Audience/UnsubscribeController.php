<?php

namespace App\Http\Controllers\Audience;

use App\Enums\Channel;
use App\Enums\HttpStatus;
use App\Helpers\Provider;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\Request;

/**
 * Opt-outs from signed links in sent messages.
 *
 * GET only forwards to the confirmation page, so link scanners cannot unsubscribe anyone.
 * POST performs the opt-out, which also serves RFC 8058 one-click List-Unsubscribe.
 */
class UnsubscribeController extends Controller
{
    public function show(Request $request, Contact $contact)
    {
        $page = Provider::config('unsubscribe_page_url');
        $action = $request->fullUrl();

        if ($page) {
            return redirect()->away($page . (str_contains($page, '?') ? '&' : '?') . http_build_query(['action' => $action]));
        }

        return (new ContactResource($contact))->additional([
            'message' => 'Send a POST request to this URL to unsubscribe.',
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    public function store(Request $request, Contact $contact)
    {
        $channel = Channel::tryFrom((string) $request->query('channel')) ?? Channel::EMAIL;

        $contact->unsubscribe($channel);

        return (new ContactResource($contact))->additional([
            'message' => 'You have been unsubscribed.',
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }
}
