<?php

namespace App\Messaging\Drivers;

use App\Messaging\Contracts\ReceivesDeliveryReports;
use App\Messaging\Contracts\ReceivesSms;
use App\Messaging\Contracts\SendsSms;
use App\Messaging\Data\DeliveryReport;
use App\Messaging\Data\InboundSms;
use App\Messaging\Data\SendResult;
use App\Messaging\Data\SmsMessage;
use Illuminate\Http\Request;

class TwilioDriver extends AbstractDriver implements ReceivesDeliveryReports, ReceivesSms, SendsSms
{
    protected string $label = 'Twilio';

    public function verify(): void
    {
        $this->ensureOk($this->client()->get($this->accountUrl() . '.json'));
    }

    public function sendSms(SmsMessage $message): SendResult
    {
        $from = $message->from ?? $this->setting('sender_id');

        $payload = array_filter(['To' => $message->to, 'Body' => $message->body, 'StatusCallback' => $message->callbackUrl]);

        if (str_starts_with((string) $from, 'MG')) {
            $payload['MessagingServiceSid'] = $from;
        } else {
            $payload['From'] = $from;
        }

        $response = $this->ensureOk(
            $this->client()->asForm()->post($this->accountUrl() . '/Messages.json', $payload)
        );

        return new SendResult($response->json('sid'), $response->json('status', 'queued'), $response->json());
    }

    /**
     * Only accepts requests carrying a valid X-Twilio-Signature for this account.
     */
    public function parseInbound(Request $request): ?InboundSms
    {
        if (! $this->hasValidSignature($request) || ! $request->filled('From')) {
            return null;
        }

        return new InboundSms($request->input('From'), (string) $request->input('Body'));
    }

    public function parseDeliveryReport(Request $request): ?DeliveryReport
    {
        $status = $request->input('MessageStatus');

        if (! $this->hasValidSignature($request) || ! in_array($status, ['delivered', 'undelivered', 'failed'], true)) {
            return null;
        }

        return new DeliveryReport(
            (string) $request->input('MessageSid'),
            $status === 'delivered',
            $status === 'delivered' ? null : trim("{$status} {$request->input('ErrorCode')}"),
        );
    }

    /**
     * Check X-Twilio-Signature: HMAC-SHA1 of the URL plus sorted POST params, keyed by the auth token.
     */
    protected function hasValidSignature(Request $request): bool
    {
        $params = $request->post();
        ksort($params);

        $payload = $request->fullUrl() . implode('', array_map(fn ($k, $v) => $k . $v, array_keys($params), $params));
        $expected = base64_encode(hash_hmac('sha1', $payload, (string) $this->credential('auth_token'), true));

        return hash_equals($expected, (string) $request->header('X-Twilio-Signature'));
    }

    protected function client()
    {
        return $this->http()->withBasicAuth($this->credential('account_sid'), $this->credential('auth_token'));
    }

    protected function accountUrl(): string
    {
        return 'https://api.twilio.com/2010-04-01/Accounts/' . $this->credential('account_sid');
    }
}
