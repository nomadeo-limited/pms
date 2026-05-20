<?php

namespace App\Program\Enums;

enum ProgramType: string
{
    case SurfCamp = 'surf_camp';
    case YogaRetreat = 'yoga_retreat';
    case LanguageImmersion = 'language_immersion';
    case Diving = 'diving';
    case Hiking = 'hiking';
    case Climbing = 'climbing';
    case Wellness = 'wellness';
    case Other = 'other';
}
