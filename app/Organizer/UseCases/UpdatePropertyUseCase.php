<?php

namespace App\Organizer\UseCases;

use App\Models\Property;
use App\Organizer\Repositories\PropertyRepositoryInterface;

class UpdatePropertyUseCase
{
    public function __construct(private PropertyRepositoryInterface $properties) {}

    public function execute(Property $property, array $data): Property
    {
        return $this->properties->update($property, $data);
    }
}
