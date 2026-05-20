<?php

namespace App\Program\Enums;

enum AddOnCategory: string
{
    case SurfClass = 'surf_class';
    case YogaClass = 'yoga_class';
    case Excursion = 'excursion';
    case EquipmentRental = 'equipment_rental';
    case Transfer = 'transfer';
    case Massage = 'massage';
    case Other = 'other';
}
