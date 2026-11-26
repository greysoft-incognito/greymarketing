<?php

namespace App\Messaging\Contracts;

use App\Messaging\Data\OpenReport;
use Illuminate\Http\Request;

interface ReportsOpens
{
    /**
     * Whether the connection is set up to send open events, so no tracking pixel is needed.
     */
    public function reportsOpens(): bool;

    /**
     * Read a provider's open event webhook. Null when the request is invalid or is not an open.
     */
    public function parseOpenReport(Request $request): ?OpenReport;
}
