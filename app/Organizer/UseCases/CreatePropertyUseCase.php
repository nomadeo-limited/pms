<?php

namespace App\Organizer\UseCases;

use App\Models\Property;
use App\Organizer\Repositories\PropertyRepositoryInterface;
use App\Tenant\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatePropertyUseCase
{
    public function __construct(
        private PropertyRepositoryInterface $properties,
        private TenantContext $tenantContext,
    ) {}

    public function execute(array $data): Property
    {
        $organizerId = $data['organizer_id'] ?? $this->tenantContext->getOrganizerId();
        $slug = $data['slug'] ?? Str::slug($data['name']);

        if ($this->properties->findBySlug($organizerId, $slug)) {
            throw ValidationException::withMessages(['slug' => 'This slug is already taken for this organizer.']);
        }

        return $this->properties->create(array_merge($data, [
            'organizer_id' => $organizerId,
            'slug' => $slug,
        ]));
    }
}
