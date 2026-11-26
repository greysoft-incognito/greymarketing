<?php

namespace App\Services\Campaigns;

use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\MessageStatus;
use App\Messaging\Contracts\ReportsOpens;
use App\Messaging\Contracts\SendsEmail;
use App\Messaging\Contracts\SendsSms;
use App\Messaging\Data\EmailMessage;
use App\Messaging\Data\SendResult;
use App\Messaging\Data\SmsMessage;
use App\Messaging\Exceptions\ProviderException;
use App\Messaging\TemplateRenderer;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\ProviderConnection;
use App\Services\Billing\UsageLimits;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Sends a batch of campaign messages through the campaign's connection.
 */
class CampaignSender
{
    public function __construct(
        protected TemplateRenderer $renderer,
        protected UsageLimits $limits,
        protected OpenTracker $opens,
    ) {}

    /**
     * Send the given pending messages.
     *
     * @param  array<int, int>  $messageIds
     * @return array{0: array<int, int>, 1: int} Ids to retry later and the delay in seconds.
     */
    public function send(Campaign $campaign, array $messageIds): array
    {
        $campaign->refresh();

        if ($campaign->status !== CampaignStatus::SENDING) {
            return [[], 0];
        }

        $connection = $campaign->sendingConnection();

        if (! $connection) {
            $this->failAll($campaign, $messageIds, 'No provider connection for this channel.');

            return [[], 0];
        }

        $driver = $connection->driver();
        $limiterKey = "campaign-send:{$connection->id}";
        $rate = (int) ($connection->settings['rate_per_second'] ?? config('messaging.default_rate_per_second', 10));

        $messages = $campaign->messages()
            ->with('contact')
            ->whereKey($messageIds)
            ->where('status', MessageStatus::PENDING)
            ->get();

        $room = $this->limits->messageRoom($campaign->workspace, $campaign->channel);
        $sent = 0;
        $retry = [];
        $delay = 0;

        foreach ($messages as $index => $message) {
            if (RateLimiter::tooManyAttempts($limiterKey, $rate)) {
                $retry = [...$retry, ...$messages->slice($index)->modelKeys()];
                $delay = max($delay, RateLimiter::availableIn($limiterKey), 1);

                break;
            }

            if (! $message->contact?->isSubscribedTo($campaign->channel)) {
                $this->mark($message, MessageStatus::SKIPPED, error: 'Unsubscribed before sending.');

                continue;
            }

            if ($room !== null && $sent >= $room) {
                $this->mark($message, MessageStatus::FAILED, error: "Your plan's monthly {$campaign->channel->value} limit has been reached.");

                continue;
            }

            RateLimiter::hit($limiterKey, 1);

            try {
                $result = $this->deliver($campaign, $connection, $driver, $message);

                $this->mark($message, MessageStatus::SENT, $result);
                $sent++;
            } catch (ProviderException|ConnectionException $e) {
                $message->attempts++;

                if ($this->isTransient($e) && $message->attempts < CampaignMessage::MAX_ATTEMPTS) {
                    $message->save();
                    $retry[] = $message->id;
                    $delay = max($delay, 30 * $message->attempts);

                    continue;
                }

                $this->mark($message, MessageStatus::FAILED, error: $e->getMessage());
            }
        }

        $this->limits->recordSent($campaign->workspace, $campaign->channel, $sent);

        $campaign->completeIfDone();

        return [$retry, $delay];
    }

    protected function deliver(Campaign $campaign, ProviderConnection $connection, mixed $driver, CampaignMessage $message): SendResult
    {
        $contact = $message->contact;
        $unsubscribeUrl = $contact->unsubscribeUrl($campaign->channel);
        $data = [...$contact->mergeData(), 'unsubscribe_url' => $unsubscribeUrl];

        if ($campaign->channel === Channel::EMAIL && $driver instanceof SendsEmail) {
            $providerTracksOpens = $driver instanceof ReportsOpens && $driver->reportsOpens();
            $html = $this->renderer->render($campaign->html, $data, escape: true);

            if ($html !== null && ! $providerTracksOpens) {
                $html = $this->opens->withPixel($html, $this->opens->pixelUrl($message));
            }

            return $driver->sendEmail(new EmailMessage(
                to: $message->to,
                subject: (string) $this->renderer->render($campaign->subject, $data),
                html: $html,
                text: $this->renderer->render($campaign->body, $data),
                toName: trim("{$contact->first_name} {$contact->last_name}") ?: null,
                headers: [
                    'List-Unsubscribe' => "<{$unsubscribeUrl}>",
                    'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
                ],
                trackOpens: $providerTracksOpens,
            ));
        }

        if ($campaign->channel === Channel::SMS && $driver instanceof SendsSms) {
            return $driver->sendSms(new SmsMessage(
                to: $message->to,
                body: (string) $this->renderer->render($campaign->body, $data),
                callbackUrl: route('webhooks.status', $connection->ulid),
            ));
        }

        throw new ProviderException("{$connection->provider} cannot send {$campaign->channel->value} messages.");
    }

    /**
     * Rate limits, provider outages and network errors are worth retrying.
     */
    protected function isTransient(ProviderException|ConnectionException $e): bool
    {
        return $e instanceof ConnectionException || $e->getCode() === 429 || $e->getCode() >= 500;
    }

    protected function mark(CampaignMessage $message, MessageStatus $status, ?SendResult $result = null, ?string $error = null): void
    {
        $message->forceFill([
            'status' => $status,
            'provider_message_id' => $result?->messageId,
            'error' => $error,
            'sent_at' => $status === MessageStatus::SENT ? now() : null,
        ])->save();
    }

    /**
     * @param  array<int, int>  $messageIds
     */
    protected function failAll(Campaign $campaign, array $messageIds, string $error): void
    {
        $campaign->messages()
            ->whereKey($messageIds)
            ->where('status', MessageStatus::PENDING)
            ->update(['status' => MessageStatus::FAILED, 'error' => $error, 'updated_at' => now()]);

        $campaign->completeIfDone();
    }
}
