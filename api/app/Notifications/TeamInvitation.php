<?php

namespace App\Notifications;

use App\Helpers\Provider;
use App\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public WorkspaceInvitation $invitation,
        public string $token
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = Provider::messageParser('team_invitation', [
            'workspace' => $this->invitation->workspace->name,
            'inviter' => $this->invitation->inviter?->fullname ?? 'A teammate',
            'role' => $this->invitation->role->label(),
            'days' => WorkspaceInvitation::LIFETIME_DAYS,
            'token' => $this->token,
            'app_url' => config('app.frontend_url', config('app.url')),
            'app_name' => Provider::config('app_name'),
        ]);

        return $message->toMail();
    }
}
