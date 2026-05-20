<?php

namespace App\Organizer\Repositories;

use App\Models\Organizer;
use Illuminate\Pagination\LengthAwarePaginator;

class OrganizerRepository implements OrganizerRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Organizer::query()->orderBy('name')->paginate($perPage);
    }

    public function findById(string $id): ?Organizer
    {
        return Organizer::find($id);
    }

    public function findBySlug(string $slug): ?Organizer
    {
        return Organizer::where('slug', $slug)->first();
    }

    public function create(array $data): Organizer
    {
        return Organizer::create($data);
    }

    public function update(Organizer $organizer, array $data): Organizer
    {
        $organizer->update($data);
        return $organizer->fresh();
    }

    public function delete(Organizer $organizer): void
    {
        $organizer->delete();
    }
}
