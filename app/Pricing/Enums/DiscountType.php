<?php

namespace App\Pricing\Enums;

enum DiscountType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
    case EarlyBird = 'early_bird';
    case LastMinute = 'last_minute';
    case LongStay = 'long_stay';
}
