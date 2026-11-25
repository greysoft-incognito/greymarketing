<?php

namespace App\Messaging\Drivers;

use App\Messaging\Contracts\SendsEmail;
use App\Messaging\Data\EmailMessage;
use App\Messaging\Data\SendResult;

class MailgunDriver extends AbstractDriver implements SendsEmail
{
    protected string $label = 'Mailgun';

    public function verify(): void
    {
        $this->ensureOk($this->client()->get($this->baseUrl() . '/v3/domains/' . $this->credential('domain')));
    }

    public function sendEmail(EmailMessage $message): SendResult
    {
        $from = $message->fromEmail ?? $this->setting('from_email');
        $name = $message->fromName ?? $this->setting('from_name');
        $to = $message->toName ? "{$message->toName} <{$message->to}>" : $message->to;

        $response = $this->ensureOk($this->client()->asForm()->post(
            $this->baseUrl() . '/v3/' . $this->credential('domain') . '/messages',
            array_filter([
                'from' => $name ? "{$name} <{$from}>" : $from,
                'to' => $to,
                'subject' => $message->subject,
                'html' => $message->html,
                'text' => $message->text,
            ])
        ));

        return new SendResult(trim((string) $response->json('id'), '<>'), 'queued', $response->json());
    }

    protected function client()
    {
        return $this->http()->withBasicAuth('api', $this->credential('api_key'));
    }

    protected function baseUrl(): string
    {
        return $this->credential('region') === 'eu' ? 'https://api.eu.mailgun.net' : 'https://api.mailgun.net';
    }
}
