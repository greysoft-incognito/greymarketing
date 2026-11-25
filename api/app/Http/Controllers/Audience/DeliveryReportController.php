<?php

namespace App\Http\Controllers\Audience;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Messaging\Contracts\ReceivesDeliveryReports;
use App\Models\ProviderConnection;
use App\Services\Campaigns\DeliveryReportRecorder;
use Illuminate\Http\Request;

/**
 * Receives delivery status callbacks from providers.
 */
class DeliveryReportController extends Controller
{
    public function __invoke(Request $request, ProviderConnection $connection, DeliveryReportRecorder $recorder)
    {
        $driver = $connection->driver();

        abort_unless($driver instanceof ReceivesDeliveryReports, HttpStatus::NOT_FOUND->value);

        $report = $driver->parseDeliveryReport($request);

        if ($report) {
            $recorder->record($connection, $report);
        }

        return response()->noContent();
    }
}
