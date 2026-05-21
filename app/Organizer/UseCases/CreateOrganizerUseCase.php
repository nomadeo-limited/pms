<?php

namespace App\Organizer\UseCases;

use App\Models\Organizer;
use App\Organizer\Repositories\OrganizerRepositoryInterface;
use Illuminate\Support\Str;

class CreateOrganizerUseCase
{
    public function __construct(private OrganizerRepositoryInterface $organizers) {}

    public function execute(array $data): Organizer
    {
        $base = Str::slug($data['name']);
        $slug = $base;
        $counter = 2;

        while ($this->organizers->findBySlug($slug)) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $this->organizers->create(array_merge($data, ['slug' => $slug]));
    }
}
