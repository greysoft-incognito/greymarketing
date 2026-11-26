<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\HttpStatus;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\MemberCollection;
use App\Http\Resources\MemberResource;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Team\TeamManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('view', $workspace);

        $members = $workspace->members()
            ->orderByRaw('case when users.id = ? then 0 else 1 end', [$workspace->owner_id])
            ->orderBy('workspace_user.created_at')
            ->paginate($request->input('limit', 30));

        return (new MemberCollection($members))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    public function update(Request $request, Workspace $workspace, User $member, TeamManager $team)
    {
        $this->authorize('manage', $workspace);

        $valid = $this->validate($request, [
            'role' => ['required', Rule::in([WorkspaceRole::ADMIN->value, WorkspaceRole::MEMBER->value])],
        ]);

        $team->changeRole($workspace, $member, WorkspaceRole::from($valid['role']));

        return (new MemberResource($workspace->members()->whereKey($member->id)->firstOrFail()))->additional([
            'message' => $member->fullname . ' is now ' . ($valid['role'] === 'admin' ? 'an admin' : 'a member') . '.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }

    public function destroy(Request $request, Workspace $workspace, User $member, TeamManager $team)
    {
        $this->authorize('manage', $workspace);

        $team->remove($workspace, $member, $request->user());

        return (new MemberResource($member))->additional([
            'message' => $member->fullname . ' was removed from ' . $workspace->name . '.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }

    /**
     * Hand the workspace to another member once the owner confirms their password.
     */
    public function transferOwnership(Request $request, Workspace $workspace, User $member, TeamManager $team)
    {
        $this->authorize('transfer', $workspace);

        $this->validate($request, [
            'password' => ['required', 'string', 'current_password:sanctum'],
        ], [
            'password.current_password' => 'Your password is not correct.',
        ]);

        $team->transferOwnership($workspace, $member, $request->user());

        return (new MemberResource($workspace->members()->whereKey($member->id)->firstOrFail()))->additional([
            'message' => $member->fullname . ' now owns ' . $workspace->name . '. You are an admin.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }
}
