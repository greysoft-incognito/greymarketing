<?php

namespace App\Http\Controllers\Admin;

use App\Enums\HttpStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlanRequest;
use App\Http\Resources\PlanCollection;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        Permission::PLANS->authorize();

        $plans = Plan::query()
            ->when($request->has('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->withCount(['subscriptions as active_subscriptions_count' => fn ($query) => $query->current()])
            ->orderBy('sort')
            ->orderBy('price')
            ->paginate(min((int) $request->input('limit', 30), 100));

        return (new PlanCollection($plans))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    public function store(PlanRequest $request)
    {
        $plan = Plan::create($request->planData());

        return (new PlanResource($plan))->additional([
            'message' => 'Plan created.',
            'status' => 'success',
            'status_code' => HttpStatus::CREATED,
        ])->response()->setStatusCode(HttpStatus::CREATED->value);
    }

    public function show(Plan $plan)
    {
        Permission::PLANS->authorize();

        return (new PlanResource($plan))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    /**
     * Limit changes apply to current subscribers at once; prices apply to new payments.
     */
    public function update(PlanRequest $request, Plan $plan)
    {
        $plan->update($request->planData());

        return (new PlanResource($plan))->additional([
            'message' => 'Plan updated.',
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    public function destroy(Plan $plan)
    {
        Permission::PLANS->authorize();

        abort_if(
            $plan->subscriptions()->exists(),
            HttpStatus::CONFLICT->value,
            'This plan has subscriptions. Deactivate it instead.'
        );

        $plan->delete();

        return (new PlanResource($plan))->additional([
            'message' => 'Plan deleted.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }
}
