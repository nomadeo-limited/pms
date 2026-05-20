<?php

namespace App\Inventory\Repositories;

use App\Models\Unit;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UnitRepositoryInterface
{
    public function paginate(string $propertyId, int $perPage = 15): LengthAwarePaginator;
    public function findById(string $id): ?Unit;
    public function findAvailableForDateRange(string $propertyId, string $checkIn, string $checkOut): Collection;
    public function create(array $data): Unit;
    public function update(Unit $unit, array $data): Unit;
    public function delete(Unit $unit): void;
}
