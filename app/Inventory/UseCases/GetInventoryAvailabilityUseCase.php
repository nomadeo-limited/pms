<?php

namespace App\Inventory\UseCases;

use App\Inventory\Repositories\UnitRepositoryInterface;
use App\Models\Property;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetInventoryAvailabilityUseCase
{
    public function __construct(private UnitRepositoryInterface $units) {}

    public function execute(string $propertyId, string $checkIn, string $checkOut): array
    {
        $property = Property::find($propertyId);
        if (!$property) {
            throw new NotFoundHttpException('Property not found.');
        }

        $available = $this->units->findAvailableForDateRange($propertyId, $checkIn, $checkOut);

        $byRoomType = $available->load('roomType')->groupBy('room_type_id');

        $roomTypes = $byRoomType->map(function (Collection $units) {
            $roomType = $units->first()->roomType;
            return [
                'room_type_id' => $roomType->id,
                'room_type_name' => $roomType->name,
                'category' => $roomType->category,
                'available_units' => $units->values()->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'bed_category' => $u->bed_category,
                    'capacity' => $u->capacity,
                ]),
                'available_count' => $units->count(),
            ];
        })->values();

        return [
            'property_id' => $propertyId,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'room_types' => $roomTypes,
            'total_available_units' => $available->count(),
        ];
    }
}
