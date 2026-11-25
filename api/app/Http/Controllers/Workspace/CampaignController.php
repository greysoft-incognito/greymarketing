<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\CampaignStatus;
use App\Enums\HttpStatus;
use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\CampaignRequest;
use App\Http\Resources\CampaignCollection;
use App\Http\Resources\CampaignMessageCollection;
use App\Http\Resources\CampaignReportResource;
use App\Http\Resources\CampaignResource;
use App\Jobs\LaunchCampaign;
use App\Models\Campaign;
use App\Models\Workspace;
use App\Services\Campaigns\CampaignReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('view', $workspace);

        $this->validate($request, [
            'status' => ['nullable', Rule::enum(CampaignStatus::class)],
        ]);

        $campaigns = $workspace->campaigns()
            ->with(['template', 'lists'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest('id')
            ->paginate($request->input('limit', 30));

        return (new CampaignCollection($campaigns))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    public function store(CampaignRequest $request, Workspace $workspace)
    {
        $campaign = new Campaign();
        $campaign->workspace()->associate($workspace);
        $campaign->user()->associate($request->user());

        $request->fillCampaign($campaign);

        return (new CampaignResource($campaign->load(['template', 'providerConnection', 'lists'])))->additional([
            'message' => 'Campaign created.',
            'status' => 'success',
            'status_code' => HttpStatus::CREATED,
        ])->response()->setStatusCode(HttpStatus::CREATED->value);
    }

    public function show(Workspace $workspace, Campaign $campaign)
    {
        $this->authorize('view', $workspace);

        return (new CampaignResource($campaign->load(['template', 'providerConnection', 'lists'])))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    public function update(CampaignRequest $request, Workspace $workspace, Campaign $campaign)
    {
        abort_unless(
            $campaign->status->isEditable(),
            HttpStatus::CONFLICT->value,
            'Only draft or scheduled campaigns can be edited.'
        );

        $request->fillCampaign($campaign);

        return (new CampaignResource($campaign->load(['template', 'providerConnection', 'lists'])))->additional([
            'message' => 'Campaign updated.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }

    public function destroy(Workspace $workspace, Campaign $campaign)
    {
        $this->authorize('contribute', $workspace);

        abort_if(
            in_array($campaign->status, [CampaignStatus::PREPARING, CampaignStatus::SENDING], true),
            HttpStatus::CONFLICT->value,
            'Cancel the campaign before deleting it.'
        );

        $campaign->delete();

        return (new CampaignResource($campaign))->additional([
            'message' => 'Campaign deleted.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }

    /**
     * Send now, or schedule when `scheduled_at` is given.
     */
    public function launch(Request $request, Workspace $workspace, Campaign $campaign)
    {
        $this->authorize('contribute', $workspace);

        $valid = $this->validate($request, [
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ]);

        abort_unless($campaign->status->isEditable(), HttpStatus::CONFLICT->value, 'This campaign has already been launched.');
        abort_unless($campaign->template, HttpStatus::UNPROCESSABLE_ENTITY->value, 'Choose a template first.');
        abort_unless($campaign->lists()->exists(), HttpStatus::UNPROCESSABLE_ENTITY->value, 'Choose at least one list first.');
        abort_unless(
            $campaign->sendingConnection(),
            HttpStatus::UNPROCESSABLE_ENTITY->value,
            "Connect a {$campaign->channel->value} provider or choose a connection first."
        );

        $scheduledAt = isset($valid['scheduled_at']) ? Carbon::parse($valid['scheduled_at']) : $campaign->scheduled_at;

        if ($scheduledAt?->isFuture()) {
            $campaign->forceFill(['status' => CampaignStatus::SCHEDULED, 'scheduled_at' => $scheduledAt])->save();
            $message = 'Campaign scheduled.';
        } else {
            LaunchCampaign::dispatch($campaign);
            $message = 'Campaign is sending.';
        }

        return (new CampaignResource($campaign->refresh()->load(['template', 'providerConnection', 'lists'])))->additional([
            'message' => $message,
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }

    /**
     * Stop a scheduled or sending campaign. Messages already sent are unaffected.
     */
    public function cancel(Workspace $workspace, Campaign $campaign)
    {
        $this->authorize('contribute', $workspace);

        abort_unless($campaign->status->isCancellable(), HttpStatus::CONFLICT->value, 'This campaign cannot be cancelled.');

        $campaign->forceFill(['status' => CampaignStatus::CANCELLED, 'completed_at' => now()])->save();

        $campaign->messages()
            ->where('status', MessageStatus::PENDING)
            ->update(['status' => MessageStatus::SKIPPED, 'error' => 'Campaign cancelled.', 'updated_at' => now()]);

        return (new CampaignResource($campaign->load(['template', 'providerConnection', 'lists'])))->additional([
            'message' => 'Campaign cancelled.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }

    public function report(Workspace $workspace, Campaign $campaign, CampaignReport $report)
    {
        $this->authorize('view', $workspace);

        return (new CampaignReportResource($report->build($campaign)))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    public function messages(Request $request, Workspace $workspace, Campaign $campaign)
    {
        $this->authorize('view', $workspace);

        $this->validate($request, [
            'status' => ['nullable', Rule::enum(MessageStatus::class)],
        ]);

        $messages = $campaign->messages()
            ->with('contact')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderBy('id')
            ->paginate($request->input('limit', 50));

        return (new CampaignMessageCollection($messages))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }
}
