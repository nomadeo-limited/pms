<?php

namespace Tests;

use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesSeeder::class);
    }

    protected function createSuperAdmin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');
        return $user;
    }

    protected function createOrganizer(string $slug = 'test-org'): Organizer
    {
        return Organizer::create([
            'name' => 'Test Org',
            'slug' => $slug,
            'email' => "{$slug}@example.com",
            'country' => 'US',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
    }

    protected function createOrganizerAdmin(Organizer $organizer): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('organizer_admin');
        $organizer->users()->attach($user->id);
        return $user;
    }

    protected function superAdminHeaders(User $user): array
    {
        $token = auth('api')->login($user);
        return [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];
    }

    protected function orgHeaders(User $user, Organizer $organizer): array
    {
        $token = auth('api')->login($user);
        return [
            'Authorization' => "Bearer {$token}",
            'X-Organizer-ID' => $organizer->id,
            'Accept' => 'application/json',
        ];
    }
}
