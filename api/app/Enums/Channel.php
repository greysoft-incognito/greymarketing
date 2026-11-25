<?php

namespace App\Enums;

enum Channel: string
{
    case EMAIL = 'email';
    case SMS = 'sms';
}
