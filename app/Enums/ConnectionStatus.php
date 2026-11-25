<?php

namespace App\Enums;

enum ConnectionStatus: string
{
    case UNTESTED = 'untested';
    case ACTIVE = 'active';
    case FAILED = 'failed';
}
