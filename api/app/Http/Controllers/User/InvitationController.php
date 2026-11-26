<?php

namespace App\Http\Controllers\User;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvitationResource;
use App\Http\Resources\WorkspaceResource;
use App\Models\WorkspaceInvitation;
use App\Services\Team\TeamManager;
use Illuminate\Http\Request;

/**
 * The invitee's side of an invite, found by the token in the emailed link.
 */
class InvitationController extends Controller
{
    public function show(string $token)
    {
        $invitation = $this->find($token)->load(['workspace', 'inviter']);

        return (new InvitationResource($invitation))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    public function accept(Request $request, string $token, TeamManager $team)
    {
        $workspace = $team->accept($this->find($token), $request->user());

        $workspace = $request->user()->workspaces()->with('currentSubscription.plan')->findOrFail($workspace->id);

        return (new WorkspaceResource($workspace))->additional([
            'message' => 'You joined ' . $workspace->name . '.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }

    public function decline(Request $request, string $token, TeamManager $team)
    {
        $invitation = $this->find($token);

        $team->decline($invitation, $request->user());

        return (new InvitationResource($invitation))->additional([
            'message' => 'Invite declined.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }

    protected function find(string $token): WorkspaceInvitation
    {
        return WorkspaceInvitation::findPending($token)
            ?? abort(HttpStatus::NOT_FOUND->value, 'This invite expired or was revoked.');
    }
}
