<?php

namespace App\Http\Controllers\Admin;

use App\Enums\HttpStatus;
use App\Enums\Permission;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionCollection;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        Permission::SUBSCRIPTIONS->authorize();

        $this->validate($request, [
            'status' => ['nullable', Rule::enum(SubscriptionStatus::class)],
            'plan' => ['nullable', 'string'],
            'workspace' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $subscriptions = Subscription::query()
            ->with(['plan', 'workspace'])
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->input('status')))
            ->when($request->filled('plan'), fn (Builder $query) => $query->whereHas(
                'plan',
                fn (Builder $plan) => $plan->where('ulid', $request->input('plan'))
            ))
            ->when($request->filled('workspace'), fn (Builder $query) => $query->whereHas(
                'workspace',
                fn (Builder $workspace) => $workspace->where('ulid', $request->input('workspace'))
            ))
            ->latest('id')
            ->paginate($request->input('limit', 30));

        return (new SubscriptionCollection($subscriptions))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    /**
     * Stop renewal for a subscription; it ends at `ends_at`.
     */
    public function cancel(Subscription $subscription)
    {
        Permission::SUBSCRIPTIONS->authorize();

        abort_unless(
            $subscription->status === SubscriptionStatus::ACTIVE,
            HttpStatus::CONFLICT->value,
            'Only active subscriptions can be cancelled.'
        );

        $subscription->cancel();

        return (new SubscriptionResource($subscription->load(['plan', 'workspace'])))->additional([
            'message' => 'Subscription cancelled.',
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }
}
