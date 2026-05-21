<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\IntegrationToken;
use App\Models\Property;
use App\Models\RoomType;
use App\Models\Unit;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    private function scaffoldIntegration(): array
    {
        $org = $this->createOrganizer('mkt-org');
        $admin = $this->createOrganizerAdmin($org);

        $property = Property::create([
            'organizer_id' => $org->id,
            'name' => 'Marketplace Camp',
            'slug' => 'marketplace-camp',
            'type' => 'surf_camp',
            'country' => 'CR',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ]);

        $roomType = RoomType::create([
            'organizer_id' => $org->id,
            'property_id' => $property->id,
            'name' => 'Dorm',
            'category' => 'dormitory',
            'is_active' => true,
        ]);

        $unit = Unit::create([
            'organizer_id' => $org->id,
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'name' => 'Bed 1',
            'capacity' => 1,
            'is_active' => true,
        ]);

        $plaintext = Str::random(64);
        IntegrationToken::create([
            'organizer_id' => $org->id,
            'name' => 'Test Token',
            'token_hash' => Hash::make($plaintext),
            'is_active' => true,
        ]);

        return compact('org', 'admin', 'property', 'unit', 'plaintext');
    }

    public function test_availability_returns_per_day_calendar(): void
    {
        ['unit' => $unit, 'plaintext' => $token] = $this->scaffoldIntegration();

        $response = $this->getJson(
            "/api/v1/integration/marketplace-camp/availability?start_date=2026-11-01&end_date=2026-11-03",
            ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json']
        );

        $response->assertOk()
            ->assertJsonPath('property_slug', 'marketplace-camp')
            ->assertJsonPath('start_date', '2026-11-01')
            ->assertJsonPath('end_date', '2026-11-03')
            ->assertJsonPath('total_units', 1)
            ->assertJsonStructure(['property_slug', 'property_id', 'start_date', 'end_date', 'total_units', 'days'])
            ->assertJsonCount(3, 'days');

        // All days fully available — no bookings yet
        $this->assertEquals(1, $response->json('days.0.available_units'));
        $this->assertEquals(0, $response->json('days.0.booked_units'));
    }

    public function test_availability_reflects_existing_bookings(): void
    {
        ['unit' => $unit, 'plaintext' => $token] = $this->scaffoldIntegration();

        $headers = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];

        // Book the only unit for Nov 1–7
        $this->postJson('/api/v1/integration/marketplace-camp/bookings', [
            'check_in_date' => '2026-11-01',
            'check_out_date' => '2026-11-08',
            'guests' => 1,
            'customer' => ['first_name' => 'Alice', 'last_name' => 'A', 'email' => 'alice@test.com'],
        ], $headers)->assertCreated();

        $response = $this->getJson(
            "/api/v1/integration/marketplace-camp/availability?start_date=2026-11-01&end_date=2026-11-08",
            $headers
        )->assertOk();

        // Nov 1–7 should be fully booked (check-out day Nov 8 is free)
        $this->assertEquals(0, $response->json('days.0.available_units')); // Nov 1
        $this->assertEquals(0, $response->json('days.6.available_units')); // Nov 7
        $this->assertEquals(1, $response->json('days.7.available_units')); // Nov 8 (checkout day, free)
    }

    public function test_marketplace_auto_assigns_unit_when_none_specified(): void
    {
        ['unit' => $unit, 'plaintext' => $token] = $this->scaffoldIntegration();

        $response = $this->postJson('/api/v1/integration/marketplace-camp/bookings', [
            'check_in_date' => '2026-11-01',
            'check_out_date' => '2026-11-08',
            'guests' => 1,
            'external_id' => 'MKT-99',
            'total_price' => 700,
            'currency' => 'USD',
            'customer' => [
                'first_name' => 'Bob',
                'last_name' => 'Smith',
                'email' => 'bob@marketplace.com',
            ],
        ], ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('source', 'marketplace')
            ->assertJsonPath('external_id', 'MKT-99')
            ->assertJsonPath('nights', 7)
            ->assertJsonPath('units.0.id', $unit->id);

        $this->assertDatabaseHas('customers', ['email' => 'bob@marketplace.com']);
    }

    public function test_marketplace_returns_422_when_no_units_available(): void
    {
        ['unit' => $unit, 'plaintext' => $token] = $this->scaffoldIntegration();

        $headers = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];

        // Fill the only unit
        $this->postJson('/api/v1/integration/marketplace-camp/bookings', [
            'check_in_date' => '2026-11-01',
            'check_out_date' => '2026-11-08',
            'guests' => 1,
            'customer' => ['first_name' => 'Alice', 'last_name' => 'A', 'email' => 'alice@test.com'],
        ], $headers)->assertCreated();

        // Second booking on same dates — no units left
        $this->postJson('/api/v1/integration/marketplace-camp/bookings', [
            'check_in_date' => '2026-11-03',
            'check_out_date' => '2026-11-10',
            'guests' => 1,
            'customer' => ['first_name' => 'Bob', 'last_name' => 'B', 'email' => 'bob@test.com'],
        ], $headers)->assertStatus(422)->assertJsonPath('message', 'Not enough capacity available: can accommodate 0 of 1 guests on the requested dates.');
    }

    public function test_integration_endpoint_rejects_invalid_token(): void
    {
        $this->scaffoldIntegration();

        $this->getJson(
            '/api/v1/integration/marketplace-camp/availability?start_date=2026-11-01&end_date=2026-11-30',
            ['Authorization' => 'Bearer invalid_token_xyz', 'Accept' => 'application/json']
        )->assertUnauthorized();
    }

    public function test_upsert_customer_creates_then_updates(): void
    {
        ['plaintext' => $token] = $this->scaffoldIntegration();

        $headers = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];

        $this->postJson('/api/v1/integration/marketplace-camp/customers', [
            'first_name' => 'Carol',
            'last_name' => 'Jones',
            'email' => 'carol@test.com',
        ], $headers)->assertOk()->assertJsonPath('first_name', 'Carol');

        $this->postJson('/api/v1/integration/marketplace-camp/customers', [
            'first_name' => 'Caroline',
            'last_name' => 'Jones',
            'email' => 'carol@test.com',
        ], $headers)->assertOk()->assertJsonPath('first_name', 'Caroline');

        $this->assertDatabaseCount('customers', 1);
    }
}
