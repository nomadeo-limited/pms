<?php

namespace App\Organizer\Enums;

enum PropertyType: string
{
    case SurfCamp = 'surf_camp';
    case YogaRetreat = 'yoga_retreat';
    case Hostel = 'hostel';
    case Guesthouse = 'guesthouse';
    case RetreatCenter = 'retreat_center';
    case Other = 'other';
}
