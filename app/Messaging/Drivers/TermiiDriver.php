<?php

namespace App\Messaging\Drivers;

use App\Messaging\Contracts\SendsSms;
use App\Messaging\Data\SendResult;
use App\Messaging\Data\SmsMessage;

class TermiiDriver extends AbstractDriver implements SendsSms
{
    protected string $label = 'Termii';

    public function verify(): void
    {
        $this->ensureOk(
            $this->http()->get($this->baseUrl() . '/api/get-balance', ['api_key' => $this->credential('api_key')])
        );
    }

    public function sendSms(SmsMessage $message): SendResult
    {
        $response = $this->ensureOk($this->http()->post($this->baseUrl() . '/api/sms/send', [
            'api_key' => $this->credential('api_key'),
            'to' => ltrim($message->to, '+'),
            'from' => $message->from ?? $this->setting('sender_id'),
            'sms' => $message->body,
            'type' => 'plain',
            'channel' => $this->setting('route', 'generic'),
        ]));

        return new SendResult($response->json('message_id'), 'sent', $response->json());
    }

    protected function baseUrl(): string
    {
        return rtrim($this->credential('base_url') ?: 'https://api.ng.termii.com', '/');
    }
}
