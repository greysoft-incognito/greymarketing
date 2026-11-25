<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\WorkspaceCollection;
use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function index(Request $request)
    {
        $workspaces = $request->user()->workspaces()->latest('workspaces.id')->paginate($request->input('limit', 30));

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

    public function update(Request $request, Workspace $workspace)
    {
        $this->authorize('manage', $workspace);

        $valid = $this->validate($request, [
            'name' => ['required', 'string', 'max:100'],
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
}
