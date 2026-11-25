<?php

namespace App\Messaging\Contracts;

use App\Messaging\Data\EmailMessage;
use App\Messaging\Data\SendResult;

interface SendsEmail extends Driver
{
    public function sendEmail(EmailMessage $message): SendResult;
}
