<?php

namespace App\Messaging\Contracts;

use App\Messaging\Data\DeliveryReport;
use Illuminate\Http\Request;

interface ReceivesDeliveryReports
{
    /**
     * Read a provider's delivery webhook. Null when the request is invalid or reports no final outcome.
     */
    public function parseDeliveryReport(Request $request): ?DeliveryReport;
}
