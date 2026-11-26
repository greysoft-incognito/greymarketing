<?php

namespace App\Notifications;

use App\Helpers\Provider;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OwnershipTransferred extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Workspace $workspace,
        public User $previousOwner
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
        $message = Provider::messageParser('workspace_ownership', [
            'fullname' => $notifiable->fullname,
            'workspace' => $this->workspace->name,
            'previous' => $this->previousOwner->fullname,
            'app_url' => config('app.frontend_url', config('app.url')),
            'app_name' => Provider::config('app_name'),
        ]);

        return $message->toMail();
    }
}
