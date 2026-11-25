<?php

namespace Tests\Unit;

use App\Messaging\Data\EmailMessage;
use App\Messaging\Data\SmsMessage;
use App\Messaging\Drivers\AfricasTalkingDriver;
use App\Messaging\Drivers\MailgunDriver;
use App\Messaging\Drivers\SendPulseDriver;
use App\Messaging\Drivers\TermiiDriver;
use App\Messaging\Drivers\TwilioDriver;
use App\Messaging\Exceptions\ProviderException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MessagingDriversTest extends TestCase
{
    public function testAfricasTalkingSendsSms(): void
    {
        Http::fake(['api.africastalking.com/version1/messaging' => Http::response([
            'SMSMessageData' => ['Recipients' => [['statusCode' => 101, 'status' => 'Success', 'messageId' => 'ATXid_1']]],
        ], 201)]);

        $driver = new AfricasTalkingDriver(['username' => 'grey', 'api_key' => 'k'], ['sender_id' => 'GREY']);
        $result = $driver->sendSms(new SmsMessage('+254711000000', 'Hi'));

        $this->assertSame('ATXid_1', $result->messageId);

        Http::assertSent(fn (Request $request) => $request->hasHeader('apiKey', 'k')
            && $request['from'] === 'GREY'
            && $request['username'] === 'grey');
    }

    public function testAfricasTalkingRejectedRecipientThrows(): void
    {
        Http::fake(['*' => Http::response([
            'SMSMessageData' => ['Recipients' => [['statusCode' => 403, 'status' => 'InvalidPhoneNumber']]],
        ], 201)]);

        $this->expectExceptionMessage("Africa's Talking: InvalidPhoneNumber");

        (new AfricasTalkingDriver(['username' => 'grey', 'api_key' => 'k']))->sendSms(new SmsMessage('123', 'Hi'));
    }

    public function testAfricasTalkingSandboxUsesSandboxHost(): void
    {
        Http::fake(['api.sandbox.africastalking.com/*' => Http::response(['UserData' => ['balance' => 'KES 0']])]);

        (new AfricasTalkingDriver(['username' => 'sandbox', 'api_key' => 'k']))->verify();

        Http::assertSentCount(1);
    }

    public function testTermiiSendsSms(): void
    {
        Http::fake(['api.ng.termii.com/api/sms/send' => Http::response(['message_id' => 'tm-9'])]);

        $result = (new TermiiDriver(['api_key' => 'k'], ['sender_id' => 'Grey', 'route' => 'dnd']))
            ->sendSms(new SmsMessage('+2348012345678', 'Hi'));

        $this->assertSame('tm-9', $result->messageId);

        Http::assertSent(fn (Request $request) => $request['to'] === '2348012345678' && $request['channel'] === 'dnd');
    }

    public function testTwilioUsesMessagingServiceWhenSenderIsAServiceSid(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1', 'status' => 'queued'], 201)]);

        $driver = new TwilioDriver(['account_sid' => 'AC1', 'auth_token' => 't'], ['sender_id' => 'MG123']);
        $result = $driver->sendSms(new SmsMessage('+15550001111', 'Hi'));

        $this->assertSame('SM1', $result->messageId);

        Http::assertSent(fn (Request $request) => $request['MessagingServiceSid'] === 'MG123'
            && str_contains($request->url(), '/Accounts/AC1/Messages.json'));
    }

    public function testTwilioFailureThrows(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Authenticate'], 401)]);

        $this->expectException(ProviderException::class);

        (new TwilioDriver(['account_sid' => 'AC1', 'auth_token' => 'bad']))->verify();
    }

    public function testMailgunSendsEmail(): void
    {
        Http::fake(['api.eu.mailgun.net/v3/mg.example.com/messages' => Http::response(['id' => '<abc@mg>', 'message' => 'Queued'])]);

        $driver = new MailgunDriver(
            ['domain' => 'mg.example.com', 'api_key' => 'key', 'region' => 'eu'],
            ['from_email' => 'news@example.com', 'from_name' => 'Grey']
        );

        $result = $driver->sendEmail(new EmailMessage('ada@example.com', 'Hello', html: '<p>Hi</p>'));

        $this->assertSame('abc@mg', $result->messageId);

        Http::assertSent(fn (Request $request) => $request['from'] === 'Grey <news@example.com>');
    }

    public function testSendPulseFetchesATokenThenSendsEmailAndSms(): void
    {
        Http::fake([
            'api.sendpulse.com/oauth/access_token' => Http::response(['access_token' => 'tok']),
            'api.sendpulse.com/smtp/emails' => Http::response(['result' => true, 'id' => 'sp-1']),
            'api.sendpulse.com/sms/send' => Http::response(['result' => true, 'campaign_id' => 77]),
        ]);

        $driver = new SendPulseDriver(['client_id' => 'id', 'client_secret' => 's'], ['from_email' => 'a@b.co', 'sender_id' => 'Grey']);

        $email = $driver->sendEmail(new EmailMessage('ada@example.com', 'Hello', html: '<p>Hi</p>'));
        $sms = $driver->sendSms(new SmsMessage('+2348012345678', 'Hi'));

        $this->assertSame('sp-1', $email->messageId);
        $this->assertSame('77', $sms->messageId);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/smtp/emails')
            && $request->hasHeader('Authorization', 'Bearer tok')
            && $request['email']['html'] === base64_encode('<p>Hi</p>'));

        $tokenRequests = collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), 'oauth'));
        $this->assertCount(1, $tokenRequests);
    }
}
