<?php

namespace App\Messaging\Drivers;

use App\Messaging\Contracts\SendsEmail;
use App\Messaging\Contracts\SendsSms;
use App\Messaging\Data\EmailMessage;
use App\Messaging\Data\SendResult;
use App\Messaging\Data\SmsMessage;
use Illuminate\Support\Facades\Cache;

class SendPulseDriver extends AbstractDriver implements SendsEmail, SendsSms
{
    protected string $label = 'SendPulse';

    protected const BASE_URL = 'https://api.sendpulse.com';

    public function verify(): void
    {
        $this->requestToken();
    }

    public function sendEmail(EmailMessage $message): SendResult
    {
        $response = $this->ensureOk($this->client()->post(self::BASE_URL . '/smtp/emails', [
            'email' => array_filter([
                'subject' => $message->subject,
                'html' => $message->html ? base64_encode($message->html) : null,
                'text' => $message->text,
                'from' => [
                    'name' => $message->fromName ?? $this->setting('from_name'),
                    'email' => $message->fromEmail ?? $this->setting('from_email'),
                ],
                'to' => [array_filter(['email' => $message->to, 'name' => $message->toName])],
                'headers' => $message->headers ?: null,
            ]),
        ]));

        return new SendResult($response->json('id'), 'queued', $response->json());
    }

    public function sendSms(SmsMessage $message): SendResult
    {
        $response = $this->ensureOk($this->client()->post(self::BASE_URL . '/sms/send', [
            'sender' => $message->from ?? $this->setting('sender_id'),
            'phones' => [ltrim($message->to, '+')],
            'body' => $message->body,
            'transliterate' => 0,
        ]));

        if ($response->json('result') === false) {
            $this->fail($response->json('message', 'Message was not accepted'));
        }

        return new SendResult((string) $response->json('campaign_id'), 'queued', $response->json());
    }

    protected function client()
    {
        return $this->http()->withToken($this->token());
    }

    /**
     * Access tokens last an hour, so they are cached per client id for slightly less.
     */
    protected function token(): string
    {
        return Cache::remember(
            'sendpulse-token:' . sha1((string) $this->credential('client_id')),
            now()->addMinutes(55),
            fn () => $this->requestToken()
        );
    }

    protected function requestToken(): string
    {
        $response = $this->ensureOk($this->http()->post(self::BASE_URL . '/oauth/access_token', [
            'grant_type' => 'client_credentials',
            'client_id' => $this->credential('client_id'),
            'client_secret' => $this->credential('client_secret'),
        ]));

        return $response->json('access_token');
    }
}
