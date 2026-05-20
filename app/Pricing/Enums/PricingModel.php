<?php

namespace App\Pricing\Enums;

enum PricingModel: string
{
    case PerNight = 'per_night';
    case PerPersonPerNight = 'per_person_per_night';
    case FixedPackage = 'fixed_package';
}
