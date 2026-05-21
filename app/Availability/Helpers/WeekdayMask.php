<?php

namespace App\Availability\Helpers;

class WeekdayMask
{
    const DAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    public static function toArray(string $mask): array
    {
        return array_values(
            array_filter(self::DAYS, fn($_, $i) => ($mask[$i] ?? '0') === '1', ARRAY_FILTER_USE_BOTH)
        );
    }

    public static function fromArray(array $days): string
    {
        $mask = '0000000';
        foreach ($days as $day) {
            $i = array_search(strtolower($day), self::DAYS);
            if ($i !== false) {
                $mask[$i] = '1';
            }
        }
        return $mask;
    }
}
