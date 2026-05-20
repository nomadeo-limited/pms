<?php

namespace App\Organizer\Repositories;

use App\Models\Organizer;
use Illuminate\Pagination\LengthAwarePaginator;

interface OrganizerRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator;
    public function findById(string $id): ?Organizer;
    public function findBySlug(string $slug): ?Organizer;
    public function create(array $data): Organizer;
    public function update(Organizer $organizer, array $data): Organizer;
    public function delete(Organizer $organizer): void;
}
