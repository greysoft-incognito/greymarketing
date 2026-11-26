<?php

namespace App\Services\Team;

use App\Enums\HttpStatus;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\TeamInvitation;
use App\Services\Billing\UsageLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Invites, roles and removals for a workspace's team. Seats come from the plan through UsageLimits.
 */
class TeamManager
{
    public function __construct(
        protected UsageLimits $limits
    ) {}

    /**
     * Invite an email address, or refresh the invite it already has.
     */
    public function invite(Workspace $workspace, User $inviter, string $email, WorkspaceRole $role): WorkspaceInvitation
    {
        $email = strtolower(trim($email));

        abort_if(
            $workspace->members()->whereRaw('lower(users.email) = ?', [$email])->exists(),
            HttpStatus::UNPROCESSABLE_ENTITY->value,
            'That person is already in this workspace.'
        );

        $invitation = $workspace->invitations()->firstOrNew(['email' => $email]);

        $holdsSeat = $invitation->exists && $invitation->expires_at->isFuture();

        abort_if(
            ! $holdsSeat && $this->limits->seatRoom($workspace) === 0,
            HttpStatus::UNPROCESSABLE_ENTITY->value,
            'All seats on your plan are in use. Remove someone or upgrade to invite more people.'
        );

        $invitation->role = $role;
        $invitation->inviter()->associate($inviter);

        $this->send($invitation);

        return $invitation;
    }

    /**
     * Email the invite again with a new link and a fresh expiry.
     */
    public function resend(WorkspaceInvitation $invitation): WorkspaceInvitation
    {
        abort_if(
            $invitation->expires_at->isPast() && $this->limits->seatRoom($invitation->workspace) === 0,
            HttpStatus::UNPROCESSABLE_ENTITY->value,
            'All seats on your plan are in use. Remove someone or upgrade to invite more people.'
        );

        $this->send($invitation);

        return $invitation;
    }

    /**
     * Add the signed-in user to the invite's workspace with the invited role.
     */
    public function accept(WorkspaceInvitation $invitation, User $user): Workspace
    {
        $this->ensureRecipient($invitation, $user);

        $workspace = $invitation->workspace;

        DB::transaction(function () use ($invitation, $workspace, $user) {
            if (! $workspace->roleOf($user)) {
                $workspace->members()->attach($user, ['role' => $invitation->role->value]);
            }

            $invitation->delete();
        });

        return $workspace;
    }

    public function decline(WorkspaceInvitation $invitation, User $user): void
    {
        $this->ensureRecipient($invitation, $user);

        $invitation->delete();
    }

    public function changeRole(Workspace $workspace, User $member, WorkspaceRole $role): void
    {
        $this->ensureNotOwner($workspace, $member, 'The owner\'s role can\'t be changed.');

        abort_if(
            $role === WorkspaceRole::OWNER,
            HttpStatus::UNPROCESSABLE_ENTITY->value,
            'A workspace has one owner.'
        );

        $workspace->members()->updateExistingPivot($member->id, ['role' => $role->value]);
    }

    public function remove(Workspace $workspace, User $member, User $actor): void
    {
        $this->ensureNotOwner($workspace, $member, 'The owner can\'t be removed.');

        abort_if(
            $member->is($actor),
            HttpStatus::UNPROCESSABLE_ENTITY->value,
            'You can\'t remove yourself. Leave the workspace instead.'
        );

        $workspace->members()->detach($member);
    }

    protected function send(WorkspaceInvitation $invitation): void
    {
        $token = $invitation->refreshToken();
        $invitation->save();

        Notification::route('mail', $invitation->email)->notify(new TeamInvitation($invitation, $token));
    }

    protected function ensureRecipient(WorkspaceInvitation $invitation, User $user): void
    {
        abort_if(
            strtolower($user->email) !== $invitation->email,
            HttpStatus::FORBIDDEN->value,
            'This invite was sent to a different email address.'
        );
    }

    protected function ensureNotOwner(Workspace $workspace, User $member, string $message): void
    {
        abort_if($workspace->owner_id === $member->id, HttpStatus::UNPROCESSABLE_ENTITY->value, $message);
    }
}
