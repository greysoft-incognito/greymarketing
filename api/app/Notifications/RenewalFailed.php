<?php

namespace App\Notifications;

use App\Helpers\Provider;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the subscriber that the last renewal charge on their saved card failed.
 */
class RenewalFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public string $reason
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->message($notifiable)->toMail();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $message = $this->message($notifiable);

        return [
            'title' => $message->subject,
            'message' => $message->toPlain(),
            'important' => true,
        ];
    }

    protected function message(object $notifiable)
    {
        $card = $this->subscription->card ?? [];

        return Provider::messageParser('billing::renewal_failed', $notifiable, [
            'plan' => $this->subscription->plan->name,
            'workspace' => $this->subscription->workspace->name,
            'card' => trim(($card['brand'] ?? 'card') . ' ending ' . ($card['last4'] ?? '')),
            'reason' => $this->reason,
            'ends_at' => $this->subscription->ends_at->toFormattedDayDateString(),
            'app_url' => config('app.frontend_url', config('app.url')),
            'app_name' => Provider::config('app_name'),
        ]);
    }
}
