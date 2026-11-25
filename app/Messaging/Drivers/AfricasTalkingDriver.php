<?php

namespace App\Messaging\Drivers;

use App\Messaging\Contracts\SendsSms;
use App\Messaging\Data\SendResult;
use App\Messaging\Data\SmsMessage;

class AfricasTalkingDriver extends AbstractDriver implements SendsSms
{
    protected string $label = "Africa's Talking";

    public function verify(): void
    {
        $this->ensureOk(
            $this->client()->get($this->baseUrl() . '/version1/user', ['username' => $this->credential('username')])
        );
    }

    public function sendSms(SmsMessage $message): SendResult
    {
        $payload = array_filter([
            'username' => $this->credential('username'),
            'to' => $message->to,
            'message' => $message->body,
            'from' => $message->from ?? $this->setting('sender_id'),
        ]);

        $response = $this->ensureOk(
            $this->client()->asForm()->post($this->baseUrl() . '/version1/messaging', $payload)
        );

        $recipient = $response->json('SMSMessageData.Recipients.0');

        if (! $recipient || ! in_array($recipient['statusCode'] ?? null, [100, 101, 102], true)) {
            $this->fail($recipient['status'] ?? $response->json('SMSMessageData.Message', 'Message was not accepted'));
        }

        return new SendResult($recipient['messageId'] ?? null, 'sent', $response->json());
    }

    protected function client()
    {
        return $this->http()->withHeaders(['apiKey' => $this->credential('api_key')]);
    }

    /**
     * The sandbox account uses its own host.
     */
    protected function baseUrl(): string
    {
        return $this->credential('username') === 'sandbox'
            ? 'https://api.sandbox.africastalking.com'
            : 'https://api.africastalking.com';
    }
}
