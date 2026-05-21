<?php

namespace App\Inventory\Repositories;

use App\Models\Room;
use Illuminate\Pagination\LengthAwarePaginator;

class RoomRepository implements RoomRepositoryInterface
{
    public function paginate(string $propertyId, int $perPage = 15): LengthAwarePaginator
    {
        return Room::where('property_id', $propertyId)
            ->when(request('room_type_id'), fn ($q, $roomTypeId) => $q->where('room_type_id', $roomTypeId))
            ->with('roomType')
            ->withCount('units')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findById(string $id): ?Room
    {
        return Room::find($id);
    }

    public function create(array $data): Room
    {
        return Room::create($data);
    }

    public function update(Room $room, array $data): Room
    {
        $room->update($data);
        return $room->fresh();
    }

    public function delete(Room $room): void
    {
        $room->delete();
    }
}
