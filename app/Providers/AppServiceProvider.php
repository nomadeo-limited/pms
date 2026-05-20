<?php

namespace App\Providers;

use App\Inventory\Repositories\RoomTypeRepository;
use App\Inventory\Repositories\RoomTypeRepositoryInterface;
use App\Inventory\Repositories\UnitRepository;
use App\Inventory\Repositories\UnitRepositoryInterface;
use App\Models\Organizer;
use App\Organizer\Repositories\OrganizerRepository;
use App\Organizer\Repositories\OrganizerRepositoryInterface;
use App\Organizer\Repositories\PropertyRepository;
use App\Organizer\Repositories\PropertyRepositoryInterface;
use App\Policies\OrganizerPolicy;
use App\Tenant\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, fn () => new TenantContext());

        $this->app->bind(OrganizerRepositoryInterface::class, OrganizerRepository::class);
        $this->app->bind(PropertyRepositoryInterface::class, PropertyRepository::class);
        $this->app->bind(RoomTypeRepositoryInterface::class, RoomTypeRepository::class);
        $this->app->bind(UnitRepositoryInterface::class, UnitRepository::class);
    }

    public function boot(): void
    {
        Gate::policy(Organizer::class, OrganizerPolicy::class);
    }
}
