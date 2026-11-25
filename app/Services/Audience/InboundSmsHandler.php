<?php

namespace App\Services\Audience;

use App\Enums\Channel;
use App\Messaging\Data\InboundSms;
use App\Models\Contact;
use App\Models\Workspace;
use Illuminate\Support\Str;

/**
 * Applies opt-out and opt-in keywords from SMS replies.
 */
class InboundSmsHandler
{
    public const STOP_WORDS = ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'];

    public const START_WORDS = ['START', 'UNSTOP', 'SUBSCRIBE'];

    public function __construct(protected ContactNormalizer $normalizer) {}

    /**
     * @return string|null The action taken: unsubscribed, resubscribed, or null.
     */
    public function handle(Workspace $workspace, InboundSms $sms): ?string
    {
        $keyword = Str::of($sms->body)->trim()->upper()->before(' ')->toString();
        $action = match (true) {
            in_array($keyword, self::STOP_WORDS, true) => 'unsubscribed',
            in_array($keyword, self::START_WORDS, true) => 'resubscribed',
            default => null,
        };

        $phone = $this->normalizer->phone($sms->from, $workspace->default_country);

        /** @var Contact|null $contact */
        $contact = $action && $phone ? $workspace->contacts()->firstWhere('phone', $phone) : null;

        if (! $contact) {
            return null;
        }

        $action === 'unsubscribed' ? $contact->unsubscribe(Channel::SMS) : $contact->resubscribe(Channel::SMS);

        return $action;
    }
}
