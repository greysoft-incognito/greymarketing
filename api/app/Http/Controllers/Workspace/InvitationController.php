<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\HttpStatus;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvitationCollection;
use App\Http\Resources\InvitationResource;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\Team\TeamManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('manage', $workspace);

        $invitations = $workspace->invitations()
            ->pending()
            ->with('inviter')
            ->latest('id')
            ->paginate($request->input('limit', 30));

        return (new InvitationCollection($invitations))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    public function store(Request $request, Workspace $workspace, TeamManager $team)
    {
        $this->authorize('manage', $workspace);

        $valid = $this->validate($request, [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in([WorkspaceRole::ADMIN->value, WorkspaceRole::MEMBER->value])],
        ]);

        $invitation = $team->invite($workspace, $request->user(), $valid['email'], WorkspaceRole::from($valid['role']));

        return (new InvitationResource($invitation->load('inviter')))->additional([
            'message' => 'Invite sent to ' . $invitation->email . '.',
            'status' => 'success',
            'status_code' => HttpStatus::CREATED,
        ])->response()->setStatusCode(HttpStatus::CREATED->value);
    }

    public function resend(Workspace $workspace, WorkspaceInvitation $invitation, TeamManager $team)
    {
        $this->authorize('manage', $workspace);

        $team->resend($invitation);

        return (new InvitationResource($invitation->load('inviter')))->additional([
            'message' => 'Invite sent to ' . $invitation->email . ' again.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }

    public function destroy(Workspace $workspace, WorkspaceInvitation $invitation)
    {
        $this->authorize('manage', $workspace);

        $invitation->delete();

        return (new InvitationResource($invitation))->additional([
            'message' => 'Invite revoked.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }
}
