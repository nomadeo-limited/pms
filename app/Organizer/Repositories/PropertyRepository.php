<?php

namespace App\Organizer\Repositories;

use App\Models\Property;
use Illuminate\Pagination\LengthAwarePaginator;

class PropertyRepository implements PropertyRepositoryInterface
{
    public function paginate(string $organizerId, int $perPage = 15): LengthAwarePaginator
    {
        return Property::withoutGlobalScopes()
            ->where('organizer_id', $organizerId)
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findById(string $id): ?Property
    {
        return Property::withoutGlobalScopes()->find($id);
    }

    public function findBySlug(string $organizerId, string $slug): ?Property
    {
        return Property::withoutGlobalScopes()
            ->where('organizer_id', $organizerId)
            ->where('slug', $slug)
            ->first();
    }

    public function create(array $data): Property
    {
        return Property::create($data);
    }

    public function update(Property $property, array $data): Property
    {
        $property->update($data);
        return $property->fresh();
    }

    public function delete(Property $property): void
    {
        $property->delete();
    }
}
