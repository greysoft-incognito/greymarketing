<?php

namespace App\Http\Controllers\Audience;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Messaging\Contracts\ReceivesSms;
use App\Models\ProviderConnection;
use App\Services\Audience\InboundSmsHandler;
use Illuminate\Http\Request;

/**
 * Receives SMS replies from providers so STOP and START keywords take effect.
 */
class InboundSmsController extends Controller
{
    public function __invoke(Request $request, ProviderConnection $connection, InboundSmsHandler $handler)
    {
        $driver = $connection->driver();

        abort_unless($driver instanceof ReceivesSms, HttpStatus::NOT_FOUND->value);

        $sms = $driver->parseInbound($request);

        abort_unless($sms, HttpStatus::BAD_REQUEST->value, 'Not a valid inbound message.');

        $handler->handle($connection->workspace, $sms);

        return response()->noContent();
    }
}
