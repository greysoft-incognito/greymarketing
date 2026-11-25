<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\HttpStatus;
use App\Helpers\Provider;
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

        return Provider::response()->success(new WorkspaceCollection($workspaces));
    }

    public function store(Request $request)
    {
        $valid = $this->validate($request, [
            'name' => ['required', 'string', 'max:100'],
        ]);

        $workspace = Workspace::createFor($request->user(), $valid['name']);

        return Provider::response()->success(new WorkspaceResource($workspace), HttpStatus::CREATED, [
            'message' => 'Workspace created.',
        ]);
    }

    public function show(Workspace $workspace)
    {
        $this->authorize('view', $workspace);

        return Provider::response()->success(new WorkspaceResource($workspace));
    }

    public function update(Request $request, Workspace $workspace)
    {
        $this->authorize('manage', $workspace);

        $valid = $this->validate($request, [
            'name' => ['required', 'string', 'max:100'],
        ]);

        $workspace->update($valid);

        return Provider::response()->success(new WorkspaceResource($workspace), HttpStatus::ACCEPTED, [
            'message' => 'Workspace updated.',
        ]);
    }

    public function destroy(Request $request, Workspace $workspace)
    {
        $this->authorize('delete', $workspace);

        if ($request->user()->workspaces()->count() < 2) {
            return Provider::response()->error([], HttpStatus::UNPROCESSABLE_ENTITY, [
                'message' => 'You cannot delete your only workspace.',
            ]);
        }

        $workspace->delete();

        return Provider::response()->success([], HttpStatus::ACCEPTED, [
            'message' => 'Workspace deleted.',
        ]);
    }
}
