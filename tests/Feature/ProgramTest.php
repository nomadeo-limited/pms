<?php

namespace Tests\Feature;

use App\Models\AddOn;
use App\Models\Program;
use App\Models\Property;
use Tests\TestCase;

class ProgramTest extends TestCase
{
    private function scaffoldProperty(): array
    {
        $org = $this->createOrganizer('prog-test');
        $admin = $this->createOrganizerAdmin($org);
        $property = Property::create([
            'organizer_id' => $org->id,
            'name' => 'Test Camp',
            'slug' => 'prog-test-camp',
            'type' => 'surf_camp',
            'country' => 'CR',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ]);
        return compact('org', 'admin', 'property');
    }

    // --- Programs ---

    public function test_create_program(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldProperty();

        $this->postJson('/api/v1/programs', [
            'property_id' => $property->id,
            'name' => 'Beginner Surf Week',
            'type' => 'surf_camp',
            'duration_days' => 7,
            'description' => 'Learn to surf in one week.',
        ], $this->orgHeaders($admin, $org))
            ->assertCreated()
            ->assertJsonPath('name', 'Beginner Surf Week')
            ->assertJsonPath('type', 'surf_camp')
            ->assertJsonPath('duration_days', 7);
    }

    public function test_list_programs_filtered_by_property(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldProperty();

        Program::create(['organizer_id' => $org->id, 'property_id' => $property->id, 'name' => 'Program A', 'type' => 'surf_camp']);

        $otherProperty = Property::create([
            'organizer_id' => $org->id, 'name' => 'Other', 'slug' => 'other-prop',
            'type' => 'yoga_retreat', 'country' => 'US', 'currency' => 'USD', 'timezone' => 'UTC',
        ]);
        Program::create(['organizer_id' => $org->id, 'property_id' => $otherProperty->id, 'name' => 'Program B', 'type' => 'yoga_retreat']);

        $this->getJson("/api/v1/programs?property_id={$property->id}", $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Program A');
    }

    public function test_show_program_includes_addons(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldProperty();

        $program = Program::create(['organizer_id' => $org->id, 'property_id' => $property->id, 'name' => 'Surf Intensive']);
        $addOn = AddOn::create(['organizer_id' => $org->id, 'property_id' => $property->id, 'name' => 'Board Rental', 'category' => 'equipment_rental', 'price' => 15, 'currency' => 'USD']);
        $program->addOns()->attach($addOn->id, ['is_default' => true]);

        $this->getJson("/api/v1/programs/{$program->id}", $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('name', 'Surf Intensive')
            ->assertJsonCount(1, 'add_ons')
            ->assertJsonPath('add_ons.0.name', 'Board Rental');
    }

    public function test_update_program(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldProperty();

        $program = Program::create(['organizer_id' => $org->id, 'property_id' => $property->id, 'name' => 'Old Name']);

        $this->putJson("/api/v1/programs/{$program->id}", ['name' => 'New Name', 'is_active' => false], $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('name', 'New Name')
            ->assertJsonPath('is_active', false);
    }

    public function test_delete_program(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldProperty();

        $program = Program::create(['organizer_id' => $org->id, 'property_id' => $property->id, 'name' => 'To Delete']);

        $this->deleteJson("/api/v1/programs/{$program->id}", [], $this->orgHeaders($admin, $org))
            ->assertNoContent();

        $this->assertDatabaseMissing('programs', ['id' => $program->id]);
    }

    // --- Add-ons ---

    public function test_create_addon(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldProperty();

        $this->postJson('/api/v1/add-ons', [
            'property_id' => $property->id,
            'name' => 'Surf Lesson',
            'category' => 'surf_class',
            'price' => 25.00,
            'currency' => 'USD',
        ], $this->orgHeaders($admin, $org))
            ->assertCreated()
            ->assertJsonPath('name', 'Surf Lesson')
            ->assertJsonPath('category', 'surf_class')
            ->assertJsonPath('price', '25.00');
    }

    public function test_attach_addon_to_program(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldProperty();

        $program = Program::create(['organizer_id' => $org->id, 'property_id' => $property->id, 'name' => 'Surf Week']);
        $addOn = AddOn::create(['organizer_id' => $org->id, 'property_id' => $property->id, 'name' => 'Airport Transfer', 'category' => 'transfer', 'price' => 30, 'currency' => 'USD']);

        $this->postJson("/api/v1/programs/{$program->id}/add-ons/{$addOn->id}", ['is_default' => true], $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonCount(1, 'add_ons')
            ->assertJsonPath('add_ons.0.pivot.is_default', true);
    }

    public function test_attach_is_idempotent(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldProperty();

        $program = Program::create(['organizer_id' => $org->id, 'property_id' => $property->id, 'name' => 'Yoga Week']);
        $addOn = AddOn::create(['organizer_id' => $org->id, 'property_id' => $property->id, 'name' => 'Massage', 'category' => 'massage', 'price' => 50, 'currency' => 'USD']);

        $headers = $this->orgHeaders($admin, $org);
        $this->postJson("/api/v1/programs/{$program->id}/add-ons/{$addOn->id}", [], $headers)->assertOk();
        $this->postJson("/api/v1/programs/{$program->id}/add-ons/{$addOn->id}", [], $headers)->assertOk();

        $this->assertDatabaseCount('program_add_ons', 1);
    }

    public function test_detach_addon_from_program(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldProperty();

        $program = Program::create(['organizer_id' => $org->id, 'property_id' => $property->id, 'name' => 'Dive Camp']);
        $addOn = AddOn::create(['organizer_id' => $org->id, 'property_id' => $property->id, 'name' => 'Equipment', 'category' => 'equipment_rental', 'price' => 20, 'currency' => 'USD']);
        $program->addOns()->attach($addOn->id, ['is_default' => false]);

        $this->deleteJson("/api/v1/programs/{$program->id}/add-ons/{$addOn->id}", [], $this->orgHeaders($admin, $org))
            ->assertNoContent();

        $this->assertDatabaseMissing('program_add_ons', ['program_id' => $program->id, 'add_on_id' => $addOn->id]);
    }

    public function test_program_not_found_returns_404(): void
    {
        ['org' => $org, 'admin' => $admin] = $this->scaffoldProperty();

        $this->getJson('/api/v1/programs/00000000-0000-0000-0000-000000000000', $this->orgHeaders($admin, $org))
            ->assertNotFound();
    }

    public function test_tenant_isolation_programs(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldProperty();

        $orgB = $this->createOrganizer('prog-org-b');
        $adminB = $this->createOrganizerAdmin($orgB);

        Program::create(['organizer_id' => $org->id, 'property_id' => $property->id, 'name' => 'Org A Program']);

        $this->getJson('/api/v1/programs', $this->orgHeaders($adminB, $orgB))
            ->assertOk()
            ->assertJsonPath('total', 0);
    }
}
