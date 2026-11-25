<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

enum PlanInterval: string
{
    case MONTH = 'month';
    case YEAR = 'year';

    /**
     * The end of one billing period that starts at the given time.
     */
    public function periodEnd(Carbon $start): Carbon
    {
        return match ($this) {
            self::MONTH => $start->copy()->addMonthNoOverflow(),
            self::YEAR => $start->copy()->addYearNoOverflow(),
        };
    }
}
