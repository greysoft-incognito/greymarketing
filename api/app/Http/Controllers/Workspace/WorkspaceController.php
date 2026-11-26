<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\WorkspaceCollection;
use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use App\Services\Audience\ContactNormalizer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function index(Request $request)
    {
        $workspaces = $request->user()->workspaces()
            ->with('currentSubscription.plan')
            ->latest('workspaces.id')
            ->paginate($request->input('limit', 30));

        return (new WorkspaceCollection($workspaces))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    public function store(Request $request)
    {
        $valid = $this->validate($request, [
            'name' => ['required', 'string', 'max:100'],
        ]);

        $workspace = Workspace::createFor($request->user(), $valid['name']);

        return (new WorkspaceResource($workspace))->additional([
            'message' => 'Workspace created.',
            'status' => 'success',
            'status_code' => HttpStatus::CREATED,
        ])->response()->setStatusCode(HttpStatus::CREATED->value);
    }

    public function show(Workspace $workspace)
    {
        $this->authorize('view', $workspace);

        return (new WorkspaceResource($workspace))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    public function update(Request $request, Workspace $workspace, ContactNormalizer $normalizer)
    {
        $this->authorize('manage', $workspace);

        $request->merge(array_filter([
            'default_country' => is_string($request->input('default_country')) ? strtoupper($request->input('default_country')) : null,
        ]));

        $valid = $this->validate($request, [
            'name' => ['sometimes', 'string', 'max:100'],
            'default_country' => ['sometimes', 'string', Rule::in($normalizer->countryCodes())],
        ], [
            'default_country.in' => 'We can\'t read phone numbers for that country.',
        ]);

        $workspace->update($valid);

        return (new WorkspaceResource($workspace))->additional([
            'message' => 'Workspace updated.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }

    public function destroy(Request $request, Workspace $workspace)
    {
        $this->authorize('delete', $workspace);

        abort_if(
            $request->user()->workspaces()->count() < 2,
            HttpStatus::UNPROCESSABLE_ENTITY->value,
            'You cannot delete your only workspace.'
        );

        $workspace->delete();

        return (new WorkspaceResource($workspace))->additional([
            'message' => 'Workspace deleted.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }

    /**
     * Remove the signed-in member from the workspace.
     */
    public function leave(Request $request, Workspace $workspace)
    {
        $this->authorize('leave', $workspace);

        $workspace->members()->detach($request->user());

        return (new WorkspaceResource($workspace))->additional([
            'message' => 'You left ' . $workspace->name . '.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }
}
