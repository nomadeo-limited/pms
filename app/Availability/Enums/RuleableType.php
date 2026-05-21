<?php

namespace App\Availability\Enums;

enum RuleableType: string
{
    case Program = 'program';
    case Unit = 'unit';
    case RoomType = 'room_type';
}
