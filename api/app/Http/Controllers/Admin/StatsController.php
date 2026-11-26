<?php

namespace App\Http\Controllers\Admin;

use App\Enums\HttpStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformStatsResource;
use App\Services\Admin\PlatformStats;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StatsController extends Controller
{
    /**
     * Platform overview: workspaces, recurring revenue, sending volume and provider health.
     */
    public function overview(PlatformStats $stats)
    {
        Permission::DASHBOARD->authorize();

        return (new PlatformStatsResource($stats->overview()))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    /**
     * Sending volume for one month, by workspace and provider.
     */
    public function usage(Request $request, PlatformStats $stats)
    {
        Permission::DASHBOARD->authorize();

        $valid = $this->validate($request, [
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = isset($valid['month']) ? Carbon::createFromFormat('Y-m-d', $valid['month'] . '-01') : now();

        return (new PlatformStatsResource($stats->usage($month)))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }
}
