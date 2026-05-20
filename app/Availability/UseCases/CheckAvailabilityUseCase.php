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
        $checkInDate = Carbon::parse($checkIn);
        $checkOutDate = Carbon::parse($checkOut);

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

        // Units blocked by a capacity=0 rule for any day in the range
        $allUnitIds = Unit::where('property_id', $propertyId)
            ->where('is_active', true)
            ->pluck('id');

        $blockedUnitIds = AvailabilityRule::where('ruleable_type', 'unit')
            ->whereIn('ruleable_id', $allUnitIds)
            ->where('capacity', 0)
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $checkIn);
            })
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->whereNull('start_date')->orWhere('start_date', '<', $checkOut);
            })
            ->pluck('ruleable_id')
            ->unique();

        $unavailableIds = $bookedUnitIds->merge($blockedUnitIds)->unique();
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
