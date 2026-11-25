<?php

use App\Messaging\Drivers;

/*
|--------------------------------------------------------------------------
| Messaging Providers
|--------------------------------------------------------------------------
|
| Each provider maps to a driver class, the channels it can send on, the
| credentials a customer must supply, and optional sending settings.
| Clients render connection forms from this catalog.
*/
return [
    'providers' => [
        'africastalking' => [
            'name' => "Africa's Talking",
            'driver' => Drivers\AfricasTalkingDriver::class,
            'channels' => ['sms'],
            'credentials' => [
                'username' => ['label' => 'Username', 'rules' => ['required', 'string']],
                'api_key' => ['label' => 'API key', 'rules' => ['required', 'string'], 'secret' => true],
            ],
            'settings' => [
                'sender_id' => ['label' => 'Sender ID', 'rules' => ['nullable', 'string', 'max:11']],
            ],
        ],
        'sendpulse' => [
            'name' => 'SendPulse',
            'driver' => Drivers\SendPulseDriver::class,
            'channels' => ['email', 'sms'],
            'credentials' => [
                'client_id' => ['label' => 'API ID', 'rules' => ['required', 'string']],
                'client_secret' => ['label' => 'API secret', 'rules' => ['required', 'string'], 'secret' => true],
            ],
            'settings' => [
                'from_email' => ['label' => 'From email', 'rules' => ['nullable', 'email']],
                'from_name' => ['label' => 'From name', 'rules' => ['nullable', 'string', 'max:100']],
                'sender_id' => ['label' => 'Sender ID', 'rules' => ['nullable', 'string', 'max:11']],
            ],
        ],
        'termii' => [
            'name' => 'Termii',
            'driver' => Drivers\TermiiDriver::class,
            'channels' => ['sms'],
            'credentials' => [
                'api_key' => ['label' => 'API key', 'rules' => ['required', 'string'], 'secret' => true],
                'base_url' => ['label' => 'Base URL', 'rules' => ['nullable', 'url']],
            ],
            'settings' => [
                'sender_id' => ['label' => 'Sender ID', 'rules' => ['nullable', 'string', 'max:11']],
                'route' => ['label' => 'Route', 'rules' => ['nullable', 'in:generic,dnd,whatsapp']],
            ],
        ],
        'twilio' => [
            'name' => 'Twilio',
            'driver' => Drivers\TwilioDriver::class,
            'channels' => ['sms'],
            'credentials' => [
                'account_sid' => ['label' => 'Account SID', 'rules' => ['required', 'string']],
                'auth_token' => ['label' => 'Auth token', 'rules' => ['required', 'string'], 'secret' => true],
            ],
            'settings' => [
                'sender_id' => ['label' => 'From number or Messaging Service SID', 'rules' => ['nullable', 'string']],
            ],
        ],
        'mailgun' => [
            'name' => 'Mailgun',
            'driver' => Drivers\MailgunDriver::class,
            'channels' => ['email'],
            'credentials' => [
                'domain' => ['label' => 'Sending domain', 'rules' => ['required', 'string']],
                'api_key' => ['label' => 'API key', 'rules' => ['required', 'string'], 'secret' => true],
                'region' => ['label' => 'Region', 'rules' => ['nullable', 'in:us,eu']],
            ],
            'settings' => [
                'from_email' => ['label' => 'From email', 'rules' => ['nullable', 'email']],
                'from_name' => ['label' => 'From name', 'rules' => ['nullable', 'string', 'max:100']],
            ],
        ],
        'smtp' => [
            'name' => 'SMTP',
            'driver' => Drivers\SmtpDriver::class,
            'channels' => ['email'],
            'credentials' => [
                'host' => ['label' => 'Host', 'rules' => ['required', 'string']],
                'port' => ['label' => 'Port', 'rules' => ['required', 'integer', 'between:1,65535']],
                'username' => ['label' => 'Username', 'rules' => ['nullable', 'string']],
                'password' => ['label' => 'Password', 'rules' => ['nullable', 'string'], 'secret' => true],
            ],
            'settings' => [
                'from_email' => ['label' => 'From email', 'rules' => ['nullable', 'email']],
                'from_name' => ['label' => 'From name', 'rules' => ['nullable', 'string', 'max:100']],
            ],
        ],
    ],
];
