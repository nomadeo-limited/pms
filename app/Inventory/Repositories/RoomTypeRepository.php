<?php

namespace App\Inventory\Repositories;

use App\Models\RoomType;
use Illuminate\Pagination\LengthAwarePaginator;

class RoomTypeRepository implements RoomTypeRepositoryInterface
{
    public function paginate(string $propertyId, int $perPage = 15): LengthAwarePaginator
    {
        return RoomType::where('property_id', $propertyId)->orderBy('name')->paginate($perPage);
    }

    public function findById(string $id): ?RoomType
    {
        return RoomType::find($id);
    }

    public function create(array $data): RoomType
    {
        return RoomType::create($data);
    }

    public function update(RoomType $roomType, array $data): RoomType
    {
        $roomType->update($data);
        return $roomType->fresh();
    }

    public function delete(RoomType $roomType): void
    {
        $roomType->delete();
    }
}
