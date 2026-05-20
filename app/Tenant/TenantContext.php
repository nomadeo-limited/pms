<?php

namespace App\Tenant;

class TenantContext
{
    private ?string $organizerId = null;

    public function setOrganizerId(?string $organizerId): void
    {
        $this->organizerId = $organizerId;
    }

    public function getOrganizerId(): ?string
    {
        return $this->organizerId;
    }

    public function hasOrganizer(): bool
    {
        return $this->organizerId !== null;
    }
}
