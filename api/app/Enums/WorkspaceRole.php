<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MEMBER = 'member';

    /**
     * Whether this role may manage workspace settings and provider connections.
     */
    public function canManage(): bool
    {
        return $this !== self::MEMBER;
    }
}
