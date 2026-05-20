<?php

namespace App\Organizer\UseCases;

use App\Models\Organizer;
use App\Organizer\Repositories\OrganizerRepositoryInterface;

class UpdateOrganizerUseCase
{
    public function __construct(private OrganizerRepositoryInterface $organizers) {}

    public function execute(Organizer $organizer, array $data): Organizer
    {
        return $this->organizers->update($organizer, $data);
    }
}
