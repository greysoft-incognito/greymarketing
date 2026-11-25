<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\WorkspaceStatsResource;
use App\Models\Workspace;
use App\Services\Campaigns\WorkspaceStats;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    /**
     * Dashboard figures: messages per day, this month against last month, and audience totals.
     */
    public function __invoke(Request $request, Workspace $workspace, WorkspaceStats $stats)
    {
        $this->authorize('view', $workspace);

        $valid = $this->validate($request, [
            'days' => ['nullable', 'integer', 'between:7,90'],
        ]);

        return (new WorkspaceStatsResource($stats->build($workspace, (int) ($valid['days'] ?? 14))))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }
}
