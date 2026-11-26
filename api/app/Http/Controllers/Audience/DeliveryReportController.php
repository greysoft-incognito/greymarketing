<?php

namespace App\Http\Controllers\Audience;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Messaging\Contracts\ReceivesDeliveryReports;
use App\Messaging\Contracts\ReportsOpens;
use App\Models\ProviderConnection;
use App\Services\Campaigns\DeliveryReportRecorder;
use App\Services\Campaigns\OpenTracker;
use Illuminate\Http\Request;

/**
 * Receives delivery status and open callbacks from providers.
 */
class DeliveryReportController extends Controller
{
    public function __invoke(Request $request, ProviderConnection $connection, DeliveryReportRecorder $recorder, OpenTracker $opens)
    {
        $driver = $connection->driver();

        abort_unless($driver instanceof ReceivesDeliveryReports, HttpStatus::NOT_FOUND->value);

        $open = $driver instanceof ReportsOpens ? $driver->parseOpenReport($request) : null;

        if ($open) {
            $opens->recordReport($connection, $open);

            return response()->noContent();
        }

        $report = $driver->parseDeliveryReport($request);

        if ($report) {
            $recorder->record($connection, $report);
        }

        return response()->noContent();
    }
}
