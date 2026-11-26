<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->roleOf($user) !== null;
    }

    /**
     * Any member may work with contacts, lists and templates.
     */
    public function contribute(User $user, Workspace $workspace): bool
    {
        return $workspace->roleOf($user) !== null;
    }

    public function manage(User $user, Workspace $workspace): bool
    {
        return (bool) $workspace->roleOf($user)?->canManage();
    }

    /**
     * Any member except the owner may leave.
     */
    public function leave(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id !== $user->id && $workspace->roleOf($user) !== null;
    }

    /**
     * Only the owner may hand the workspace to another member.
     */
    public function transfer(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }
}
