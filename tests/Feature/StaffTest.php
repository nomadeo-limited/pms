<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class StaffTest extends TestCase
{
    public function test_list_staff(): void
    {
        $org = $this->createOrganizer('staff-test');
        $admin = $this->createOrganizerAdmin($org);

        $this->getJson('/api/v1/staff', $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonStructure(['data', 'total']);
    }

    public function test_invite_staff_member(): void
    {
        $org = $this->createOrganizer('staff-invite');
        $admin = $this->createOrganizerAdmin($org);

        $this->postJson('/api/v1/staff/invite', [
            'name' => 'New Staff',
            'email' => 'staff@test.com',
            'role' => 'organizer_staff',
        ], $this->orgHeaders($admin, $org))
            ->assertCreated()
            ->assertJsonPath('email', 'staff@test.com');

        $this->assertDatabaseHas('users', ['email' => 'staff@test.com']);
    }

    public function test_invite_duplicate_email_fails(): void
    {
        $org = $this->createOrganizer('staff-dup');
        $admin = $this->createOrganizerAdmin($org);

        User::factory()->create(['email' => 'existing@test.com']);

        $this->postJson('/api/v1/staff/invite', [
            'name' => 'Dup Staff',
            'email' => 'existing@test.com',
            'role' => 'organizer_staff',
        ], $this->orgHeaders($admin, $org))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_staff_role(): void
    {
        $org = $this->createOrganizer('staff-role');
        $admin = $this->createOrganizerAdmin($org);
        $staff = $this->createOrganizerAdmin($org);

        $this->putJson("/api/v1/staff/{$staff->id}/role", [
            'role' => 'organizer_staff',
        ], $this->orgHeaders($admin, $org))
            ->assertOk();

        $this->assertTrue($staff->fresh()->hasRole('organizer_staff'));
        $this->assertFalse($staff->fresh()->hasRole('organizer_admin'));
    }

    public function test_remove_staff_member(): void
    {
        $org = $this->createOrganizer('staff-remove');
        $admin = $this->createOrganizerAdmin($org);
        $staff = $this->createOrganizerAdmin($org);

        $this->deleteJson("/api/v1/staff/{$staff->id}", [], $this->orgHeaders($admin, $org))
            ->assertNoContent();

        $this->assertDatabaseMissing('organizer_user', [
            'organizer_id' => $org->id,
            'user_id' => $staff->id,
        ]);
    }

    public function test_update_role_unknown_user_returns_404(): void
    {
        $org = $this->createOrganizer('staff-404');
        $admin = $this->createOrganizerAdmin($org);

        $this->putJson('/api/v1/staff/00000000-0000-0000-0000-000000000000/role', [
            'role' => 'organizer_staff',
        ], $this->orgHeaders($admin, $org))
            ->assertNotFound();
    }
}
