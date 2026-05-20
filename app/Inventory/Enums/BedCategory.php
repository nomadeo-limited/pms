<?php

namespace App\Inventory\Enums;

enum BedCategory: string
{
    case BunkBed = 'bunk_bed';
    case Single = 'single';
    case Double = 'double';
    case Queen = 'queen';
    case King = 'king';
    case Futon = 'futon';
    case Hammock = 'hammock';
}
