<?php

namespace App\Listeners;

use App\Models\Workspace;
use Illuminate\Auth\Events\Registered;

class CreatePersonalWorkspace
{
    /**
     * Give every new user a workspace they own.
     */
    public function handle(Registered $event): void
    {
        Workspace::createFor($event->user);
    }
}
