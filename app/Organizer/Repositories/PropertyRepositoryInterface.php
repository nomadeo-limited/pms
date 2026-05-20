<?php

namespace App\Organizer\Repositories;

use App\Models\Property;
use Illuminate\Pagination\LengthAwarePaginator;

interface PropertyRepositoryInterface
{
    public function paginate(string $organizerId, int $perPage = 15): LengthAwarePaginator;
    public function findById(string $id): ?Property;
    public function findBySlug(string $organizerId, string $slug): ?Property;
    public function create(array $data): Property;
    public function update(Property $property, array $data): Property;
    public function delete(Property $property): void;
}
