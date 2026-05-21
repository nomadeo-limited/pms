<?php

namespace App\Organizer\UseCases;

use App\Models\Property;
use App\Organizer\Repositories\PropertyRepositoryInterface;
use App\Tenant\TenantContext;
use Illuminate\Support\Str;

class CreatePropertyUseCase
{
    public function __construct(
        private PropertyRepositoryInterface $properties,
        private TenantContext $tenantContext,
    ) {}

    public function execute(array $data): Property
    {
        $organizerId = $data['organizer_id'] ?? $this->tenantContext->getOrganizerId();
        $base = Str::slug($data['name']);
        $slug = $base;
        $counter = 2;

        while ($this->properties->findBySlug($organizerId, $slug)) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $this->properties->create(array_merge($data, [
            'organizer_id' => $organizerId,
            'slug' => $slug,
        ]));
    }
}
