<?php

namespace App\Availability\UseCases;

use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GetAvailabilityCalendarUseCase
{
    public function execute(string $propertyId, string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $units = Unit::where('property_id', $propertyId)
            ->where('is_active', true)
            ->pluck('id');

        $totalUnits = $units->count();

        // All confirmed bookings that overlap the range
        $bookedUnitsByDay = $this->getBookedUnitIdsByDay($propertyId, $startDate, $endDate);

        // Availability rules that block units in the range
        $blockingRules = AvailabilityRule::where('ruleable_type', 'unit')
            ->whereIn('ruleable_id', $units)
            ->where('capacity', 0)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $startDate);
            })
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $endDate);
            })
            ->get()
            ->groupBy('ruleable_id');

        $days = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $dateKey = $cursor->toDateString();
            $dayOfWeek = $cursor->dayOfWeek; // 0=Sun … 6=Sat

            $blockedUnitIds = collect();

            // Units blocked by availability rules on this day
            foreach ($blockingRules as $unitId => $rules) {
                foreach ($rules as $rule) {
                    if ($this->ruleAppliesOnDay($rule, $cursor, $dayOfWeek)) {
                        $blockedUnitIds->push($unitId);
                        break;
                    }
                }
            }

            $bookedIds = $bookedUnitsByDay[$dateKey] ?? collect();
            $unavailableCount = $bookedIds->merge($blockedUnitIds)->unique()->count();

            $days[] = [
                'date' => $dateKey,
                'total_units' => $totalUnits,
                'booked_units' => $bookedIds->count(),
                'blocked_units' => $blockedUnitIds->count(),
                'available_units' => max(0, $totalUnits - $unavailableCount),
            ];

            $cursor->addDay();
        }

        return [
            'property_id' => $propertyId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_units' => $totalUnits,
            'days' => $days,
        ];
    }

    private function getBookedUnitIdsByDay(string $propertyId, string $startDate, string $endDate): Collection
    {
        $bookings = Booking::where('property_id', $propertyId)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('check_in_date', '<=', $endDate)
            ->where('check_out_date', '>', $startDate)
            ->with('units:id,booking_units.booking_id')
            ->get();

        $map = collect();

        foreach ($bookings as $booking) {
            $checkIn = Carbon::parse($booking->check_in_date);
            // Checkout day itself is free (guest leaves that morning)
            $checkOut = Carbon::parse($booking->check_out_date)->subDay();

            $cursor = $checkIn->copy();
            while ($cursor->lte($checkOut)) {
                $date = $cursor->toDateString();
                if (!isset($map[$date])) {
                    $map[$date] = collect();
                }
                foreach ($booking->units as $unit) {
                    $map[$date]->push($unit->id);
                }
                $cursor->addDay();
            }
        }

        return $map;
    }

    private function ruleAppliesOnDay(AvailabilityRule $rule, Carbon $day, int $dayOfWeek): bool
    {
        if ($rule->start_date && $day->lt($rule->start_date)) {
            return false;
        }
        if ($rule->end_date && $day->gt($rule->end_date)) {
            return false;
        }
        // weekday_mask: index 0=Sun … 6=Sat, '1' means allowed/open, '0' means blocked
        if ($rule->weekday_mask && $rule->weekday_mask !== '1111111') {
            return $rule->weekday_mask[$dayOfWeek] === '0';
        }
        return true;
    }
}
