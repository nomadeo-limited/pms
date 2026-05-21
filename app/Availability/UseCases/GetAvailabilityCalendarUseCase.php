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
            ->get(['id', 'room_type_id']);

        $allUnitIds = $units->pluck('id');
        $roomTypeIds = $units->pluck('room_type_id')->filter()->unique()->values();
        $totalUnits = $allUnitIds->count();

        $bookedUnitsByDay = $this->getBookedUnitIdsByDay($propertyId, $startDate, $endDate);

        $blockingRules = $this->getBlockingRules($allUnitIds, $roomTypeIds, $startDate, $endDate);

        // Pre-compute: for room_type rules, map rule → unit IDs affected
        $roomTypeToUnitIds = $units->groupBy('room_type_id')->map(fn($g) => $g->pluck('id'));

        $days = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $dateKey = $cursor->toDateString();

            $blockedUnitIds = collect();

            foreach ($blockingRules as $rule) {
                if (!$rule->appliesOnDay($cursor)) {
                    continue;
                }
                if ($rule->ruleable_type === 'unit') {
                    $blockedUnitIds->push($rule->ruleable_id);
                } elseif ($rule->ruleable_type === 'room_type') {
                    $blockedUnitIds = $blockedUnitIds->merge(
                        $roomTypeToUnitIds[$rule->ruleable_id] ?? collect()
                    );
                }
            }

            $bookedIds = $bookedUnitsByDay[$dateKey] ?? collect();
            $unavailableCount = $bookedIds->merge($blockedUnitIds)->unique()->count();

            $days[] = [
                'date' => $dateKey,
                'total_units' => $totalUnits,
                'booked_units' => $bookedIds->count(),
                'blocked_units' => $blockedUnitIds->unique()->count(),
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

    private function getBlockingRules(Collection $unitIds, Collection $roomTypeIds, string $startDate, string $endDate): Collection
    {
        return AvailabilityRule::where('capacity', 0)
            ->where(function ($q) use ($unitIds, $roomTypeIds) {
                $q->where(fn($q2) => $q2->where('ruleable_type', 'unit')->whereIn('ruleable_id', $unitIds));
                if ($roomTypeIds->isNotEmpty()) {
                    $q->orWhere(fn($q2) => $q2->where('ruleable_type', 'room_type')->whereIn('ruleable_id', $roomTypeIds));
                }
            })
            ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $startDate))
            ->where(fn($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $endDate))
            ->get();
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
}
