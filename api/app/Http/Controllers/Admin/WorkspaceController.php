<?php

namespace App\Http\Controllers\Admin;

use App\Enums\HttpStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\BillingResource;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\WorkspaceCollection;
use App\Models\Plan;
use App\Models\Workspace;
use App\Models\WorkspaceUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function index(Request $request)
    {
        Permission::WORKSPACES->authorize();

        $this->validate($request, [
            'search' => ['nullable', 'string', 'max:100'],
            'plan' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $workspaces = Workspace::query()
            ->with(['owner', 'currentSubscription.plan'])
            ->withCount(['contacts', 'campaigns'])
            ->withSum(['usage as emails_this_month' => fn (Builder $query) => $query->where('period', WorkspaceUsage::currentPeriod())], 'emails_sent')
            ->withSum(['usage as sms_this_month' => fn (Builder $query) => $query->where('period', WorkspaceUsage::currentPeriod())], 'sms_sent')
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $term = '%' . mb_strtolower($request->input('search')) . '%';

                $query->where(fn (Builder $q) => $q
                    ->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereHas('owner', fn (Builder $owner) => $owner->whereRaw('LOWER(email) LIKE ?', [$term])));
            })
            ->when($request->filled('plan'), fn (Builder $query) => $query->whereHas(
                'subscriptions',
                fn (Builder $q) => $q->current()->whereHas('plan', fn (Builder $plan) => $plan->where('ulid', $request->input('plan')))
            ))
            ->latest('id')
            ->paginate($request->input('limit', 30));

        return (new WorkspaceCollection($workspaces))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    /**
     * Plan, subscription and usage for one workspace.
     */
    public function show(Workspace $workspace)
    {
        Permission::WORKSPACES->authorize();

        return (new BillingResource($workspace->load('currentSubscription.plan')))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    /**
     * Put a workspace on a plan without payment, e.g. for support or a trial.
     */
    public function grant(Request $request, Workspace $workspace)
    {
        Permission::SUBSCRIPTIONS->authorize();

        $valid = $this->validate($request, [
            'plan' => ['required', 'string', 'exists:plans,ulid'],
            'ends_at' => ['nullable', 'date', 'after:now'],
        ]);

        $plan = Plan::query()->where('ulid', $valid['plan'])->firstOrFail();

        $subscription = $workspace->subscriptions()->make([
            'amount' => 0,
            'currency' => $plan->currency,
            'ends_at' => $valid['ends_at'] ?? null,
        ]);
        $subscription->plan()->associate($plan);
        $subscription->user()->associate($request->user());
        $subscription->save();

        $subscription->activate();

        return (new SubscriptionResource($subscription->load('plan')))->additional([
            'message' => "{$workspace->name} is now on {$plan->name}.",
            'status' => 'success',
            'status_code' => HttpStatus::CREATED,
        ])->response()->setStatusCode(HttpStatus::CREATED->value);
    }
}
