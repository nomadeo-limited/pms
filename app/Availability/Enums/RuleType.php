<?php

namespace App\Availability\Enums;

enum RuleType: string
{
    case Daily = 'daily';
    case SpecificDates = 'specific_dates';
    case DateRange = 'date_range';
}
