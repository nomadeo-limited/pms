<?php

namespace App\Inventory\Repositories;

use App\Models\RoomType;
use Illuminate\Pagination\LengthAwarePaginator;

interface RoomTypeRepositoryInterface
{
    public function paginate(string $propertyId, int $perPage = 15): LengthAwarePaginator;
    public function findById(string $id): ?RoomType;
    public function create(array $data): RoomType;
    public function update(RoomType $roomType, array $data): RoomType;
    public function delete(RoomType $roomType): void;
}
