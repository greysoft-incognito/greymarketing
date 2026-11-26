<?php

namespace App\Messaging\Contracts;

use App\Messaging\Data\EngagementReport;
use Illuminate\Http\Request;

interface ReportsEngagement
{
    /**
     * Whether the connection is set up to send open and click events, so emails need no tracking of our own.
     */
    public function reportsEngagement(): bool;

    /**
     * Read a provider's open or click webhook. Null when the request is invalid or is neither.
     */
    public function parseEngagementReport(Request $request): ?EngagementReport;
}
