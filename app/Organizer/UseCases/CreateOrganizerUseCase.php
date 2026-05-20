<?php

namespace App\Organizer\UseCases;

use App\Models\Organizer;
use App\Organizer\Repositories\OrganizerRepositoryInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateOrganizerUseCase
{
    public function __construct(private OrganizerRepositoryInterface $organizers) {}

    public function execute(array $data): Organizer
    {
        $slug = $data['slug'] ?? Str::slug($data['name']);

        if ($this->organizers->findBySlug($slug)) {
            throw ValidationException::withMessages(['slug' => 'This slug is already taken.']);
        }

        return $this->organizers->create(array_merge($data, ['slug' => $slug]));
    }
}
