<?php

namespace App\Messaging\Contracts;

use App\Messaging\Data\SendResult;
use App\Messaging\Data\SmsMessage;

interface SendsSms extends Driver
{
    public function sendSms(SmsMessage $message): SendResult;
}
