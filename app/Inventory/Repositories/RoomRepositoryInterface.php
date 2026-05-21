<?php

namespace App\Inventory\Repositories;

use App\Models\Room;
use Illuminate\Pagination\LengthAwarePaginator;

interface RoomRepositoryInterface
{
    public function paginate(string $propertyId, int $perPage = 15): LengthAwarePaginator;
    public function findById(string $id): ?Room;
    public function create(array $data): Room;
    public function update(Room $room, array $data): Room;
    public function delete(Room $room): void;
}
