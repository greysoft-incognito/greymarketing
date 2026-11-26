<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\Channel;
use App\Enums\HttpStatus;
use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignPerformanceCollection;
use App\Http\Resources\WorkspaceReportResource;
use App\Models\Workspace;
use App\Services\Campaigns\WorkspaceReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    /**
     * Delivery totals, daily volume, failure reasons and provider breakdown for a period.
     */
    public function show(Request $request, Workspace $workspace, WorkspaceReport $report)
    {
        $this->authorize('view', $workspace);

        [$days, $channel] = $this->filters($request);

        return (new WorkspaceReportResource($report->build($workspace, $days, $channel)))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    /**
     * Campaigns started in the period with their delivery counts, newest first.
     */
    public function campaigns(Request $request, Workspace $workspace, WorkspaceReport $report)
    {
        $this->authorize('view', $workspace);

        [$days, $channel] = $this->filters($request);

        $counts = collect([MessageStatus::SENT, MessageStatus::DELIVERED, MessageStatus::FAILED])
            ->mapWithKeys(fn (MessageStatus $status) => [
                "messages as {$status->value}_count" => fn (Builder $query) => $query->where('status', $status),
            ])
            ->all();

        $campaigns = $report->campaigns($workspace, now()->startOfDay()->subDays($days - 1), $channel)
            ->with('providerConnection')
            ->withCount($counts)
            ->latest('started_at')
            ->latest('id')
            ->paginate($request->input('limit', 20));

        return (new CampaignPerformanceCollection($campaigns))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    /**
     * @return array{0: int, 1: Channel|null}
     */
    protected function filters(Request $request): array
    {
        $valid = $this->validate($request, [
            'days' => ['nullable', 'integer', 'between:7,90'],
            'channel' => ['nullable', Rule::enum(Channel::class)],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);

        return [(int) ($valid['days'] ?? 30), isset($valid['channel']) ? Channel::from($valid['channel']) : null];
    }
}
