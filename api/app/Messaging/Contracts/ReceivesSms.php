<?php

namespace App\Messaging\Contracts;

use App\Messaging\Data\InboundSms;
use Illuminate\Http\Request;

interface ReceivesSms
{
    /**
     * Read a provider's inbound message webhook, or null when the request is not one.
     */
    public function parseInbound(Request $request): ?InboundSms;
}
