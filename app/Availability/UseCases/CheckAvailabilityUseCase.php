<?php

namespace App\Availability\UseCases;

use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\Unit;
use Illuminate\Support\Carbon;

class CheckAvailabilityUseCase
{
    public function execute(string $propertyId, string $checkIn, string $checkOut, int $minUnits = 1): array
    {
        $units = Unit::where('property_id', $propertyId)
            ->where('is_active', true)
            ->get(['id', 'room_type_id']);

        $allUnitIds = $units->pluck('id');
        $roomTypeIds = $units->pluck('room_type_id')->filter()->unique()->values();

        // Units booked for any part of the requested range
        $bookedUnitIds = Booking::where('property_id', $propertyId)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('check_in_date', '<', $checkOut)
            ->where('check_out_date', '>', $checkIn)
            ->with('units:id')
            ->get()
            ->pluck('units')
            ->flatten()
            ->pluck('id')
            ->unique();

        // Unit-level blocking rules
        $unitBlockedIds = AvailabilityRule::where('ruleable_type', 'unit')
            ->whereIn('ruleable_id', $allUnitIds)
            ->where('capacity', 0)
            ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $checkIn))
            ->where(fn($q) => $q->whereNull('start_date')->orWhere('start_date', '<', $checkOut))
            ->pluck('ruleable_id')
            ->unique();

        // Room-type-level blocking rules: all units of that room type are blocked
        $blockedRoomTypeIds = $roomTypeIds->isNotEmpty()
            ? AvailabilityRule::where('ruleable_type', 'room_type')
                ->whereIn('ruleable_id', $roomTypeIds)
                ->where('capacity', 0)
                ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $checkIn))
                ->where(fn($q) => $q->whereNull('start_date')->orWhere('start_date', '<', $checkOut))
                ->pluck('ruleable_id')
                ->unique()
            : collect();

        $roomTypeBlockedUnitIds = $units->whereIn('room_type_id', $blockedRoomTypeIds)->pluck('id');

        $unavailableIds = $bookedUnitIds
            ->merge($unitBlockedIds)
            ->merge($roomTypeBlockedUnitIds)
            ->unique();

        $availableCount = $allUnitIds->diff($unavailableIds)->count();

        return [
            'property_id' => $propertyId,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'available' => $availableCount >= $minUnits,
            'available_units' => $availableCount,
            'total_units' => $allUnitIds->count(),
        ];
    }
}
