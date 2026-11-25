<?php

namespace App\Messaging\Drivers;

use App\Messaging\Contracts\SendsEmail;
use App\Messaging\Data\EmailMessage;
use App\Messaging\Data\SendResult;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class SmtpDriver extends AbstractDriver implements SendsEmail
{
    protected string $label = 'SMTP';

    public function verify(): void
    {
        try {
            $transport = $this->transport();
            $transport->start();
            $transport->stop();
        } catch (TransportExceptionInterface $e) {
            $this->fail($e->getMessage());
        }
    }

    public function sendEmail(EmailMessage $message): SendResult
    {
        $email = (new Email())
            ->from(new Address(
                $message->fromEmail ?? $this->setting('from_email'),
                $message->fromName ?? $this->setting('from_name', '')
            ))
            ->to(new Address($message->to, $message->toName ?? ''))
            ->subject($message->subject);

        if ($message->html) {
            $email->html($message->html);
        }

        if ($message->text) {
            $email->text($message->text);
        }

        try {
            $sent = $this->transport()->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->fail($e->getMessage());
        }

        return new SendResult($sent?->getMessageId(), 'sent');
    }

    /**
     * Port 465 uses implicit TLS, other ports upgrade with STARTTLS when offered.
     */
    protected function transport(): EsmtpTransport
    {
        $port = (int) $this->credential('port', 587);

        $transport = new EsmtpTransport($this->credential('host'), $port, $port === 465);
        $transport->setUsername((string) $this->credential('username'));
        $transport->setPassword((string) $this->credential('password'));

        return $transport;
    }
}
