<?php

namespace App\Inventory\Enums;

enum RoomCategory: string
{
    case SharedDorm = 'shared_dorm';
    case PrivateRoom = 'private_room';
    case Bungalow = 'bungalow';
    case Tent = 'tent';
    case VanCabin = 'van_cabin';
    case BoatCabin = 'boat_cabin';
    case Other = 'other';
}
