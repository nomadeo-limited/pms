<?php

namespace App\Inventory\Repositories;

use App\Models\Unit;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class UnitRepository implements UnitRepositoryInterface
{
    public function paginate(string $propertyId, int $perPage = 15): LengthAwarePaginator
    {
        return Unit::where('property_id', $propertyId)->with('roomType')->orderBy('name')->paginate($perPage);
    }

    public function findById(string $id): ?Unit
    {
        return Unit::find($id);
    }

    public function findAvailableForDateRange(string $propertyId, string $checkIn, string $checkOut): Collection
    {
        return Unit::where('property_id', $propertyId)
            ->where('is_active', true)
            ->whereDoesntHave('bookings', function ($query) use ($checkIn, $checkOut) {
                $query->whereNotIn('status', ['cancelled', 'no_show'])
                    ->where('check_in_date', '<', $checkOut)
                    ->where('check_out_date', '>', $checkIn);
            })
            ->get();
    }

    public function create(array $data): Unit
    {
        return Unit::create($data);
    }

    public function update(Unit $unit, array $data): Unit
    {
        $unit->update($data);
        return $unit->fresh();
    }

    public function delete(Unit $unit): void
    {
        $unit->delete();
    }
}
