<?php
namespace App\Housekeeping\Enums;
enum HousekeepingStatus: string {
    case Dirty = 'dirty';
    case Clean = 'clean';
    case Inspected = 'inspected';
    case Occupied = 'occupied';
    case OutOfService = 'out_of_service';
}
