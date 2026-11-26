<?php

namespace App\Messaging\Drivers;

use App\Messaging\Contracts\ReceivesDeliveryReports;
use App\Messaging\Contracts\ReportsOpens;
use App\Messaging\Contracts\SendsEmail;
use App\Messaging\Data\DeliveryReport;
use App\Messaging\Data\EmailMessage;
use App\Messaging\Data\OpenReport;
use App\Messaging\Data\SendResult;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MailgunDriver extends AbstractDriver implements ReceivesDeliveryReports, ReportsOpens, SendsEmail
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
                'o:tracking-opens' => $message->trackOpens ? 'yes' : null,
                ...collect($message->headers)->mapWithKeys(fn ($value, $name) => ["h:{$name}" => $value])->all(),
            ])
        ));

        return new SendResult(trim((string) $response->json('id'), '<>'), 'queued', $response->json());
    }

    /**
     * Needs the webhook signing key; temporary failures are skipped because Mailgun retries them.
     */
    public function parseDeliveryReport(Request $request): ?DeliveryReport
    {
        if (! $this->hasValidSignature($request)) {
            return null;
        }

        $event = $request->input('event-data.event');
        $id = $request->input('event-data.message.headers.message-id');

        if (! $id || ! in_array($event, ['delivered', 'failed'], true)) {
            return null;
        }

        if ($event === 'failed' && $request->input('event-data.severity') !== 'permanent') {
            return null;
        }

        return new DeliveryReport(
            $id,
            $event === 'delivered',
            $event === 'failed' ? ($request->input('event-data.delivery-status.message') ?: 'Delivery failed') : null,
        );
    }

    /**
     * Opens arrive on the same webhook as delivery events, so they need the signing key too.
     */
    public function reportsOpens(): bool
    {
        return (string) $this->credential('webhook_signing_key') !== '';
    }

    public function parseOpenReport(Request $request): ?OpenReport
    {
        $id = $request->input('event-data.message.headers.message-id');

        if (! $this->hasValidSignature($request) || $request->input('event-data.event') !== 'opened' || ! $id) {
            return null;
        }

        $timestamp = $request->input('event-data.timestamp');

        return new OpenReport($id, is_numeric($timestamp) ? Carbon::createFromTimestamp((int) $timestamp) : null);
    }

    protected function hasValidSignature(Request $request): bool
    {
        $key = (string) $this->credential('webhook_signing_key');
        $signature = $request->input('signature', []);
        $expected = hash_hmac('sha256', ($signature['timestamp'] ?? '') . ($signature['token'] ?? ''), $key);

        return $key !== '' && hash_equals($expected, (string) ($signature['signature'] ?? ''));
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
