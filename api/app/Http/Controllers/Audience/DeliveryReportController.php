<?php

namespace App\Http\Controllers\Audience;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Messaging\Contracts\ReceivesDeliveryReports;
use App\Messaging\Contracts\ReportsEngagement;
use App\Models\ProviderConnection;
use App\Services\Campaigns\DeliveryReportRecorder;
use App\Services\Campaigns\EmailTracker;
use Illuminate\Http\Request;

/**
 * Receives delivery status, open and click callbacks from providers.
 */
class DeliveryReportController extends Controller
{
    public function __invoke(Request $request, ProviderConnection $connection, DeliveryReportRecorder $recorder, EmailTracker $tracker)
    {
        $driver = $connection->driver();

        abort_unless($driver instanceof ReceivesDeliveryReports, HttpStatus::NOT_FOUND->value);

        $engagement = $driver instanceof ReportsEngagement ? $driver->parseEngagementReport($request) : null;

        if ($engagement) {
            $tracker->recordReport($connection, $engagement);

            return response()->noContent();
        }

        $report = $driver->parseDeliveryReport($request);

        if ($report) {
            $recorder->record($connection, $report);
        }

        return response()->noContent();
    }
}
