<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Property;
use App\Models\RoomType;
use App\Models\Unit;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    private function scaffoldOrg(): array
    {
        $org = $this->createOrganizer('booking-test');
        $admin = $this->createOrganizerAdmin($org);

        $property = Property::create([
            'organizer_id' => $org->id,
            'name' => 'Test Camp',
            'slug' => 'test-camp',
            'type' => 'surf_camp',
            'country' => 'CR',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ]);

        $roomType = RoomType::create([
            'organizer_id' => $org->id,
            'property_id' => $property->id,
            'name' => 'Dorm',
            'category' => 'shared_dorm',
            'max_capacity' => 6,
        ]);

        $unit = Unit::create([
            'organizer_id' => $org->id,
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'name' => 'Bed A1',
            'bed_category' => 'single',
            'capacity' => 1,
        ]);

        $customer = Customer::create([
            'organizer_id' => $org->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@test.com',
        ]);

        return compact('org', 'admin', 'property', 'unit', 'customer');
    }

    public function test_create_booking_with_unit(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property, 'unit' => $unit, 'customer' => $customer] = $this->scaffoldOrg();

        $response = $this->postJson('/api/v1/bookings', [
            'property_id' => $property->id,
            'customer_id' => $customer->id,
            'check_in_date' => '2026-08-01',
            'check_out_date' => '2026-08-08',
            'guests' => 1,
            'total_price' => 350,
            'currency' => 'USD',
            'unit_ids' => [['unit_id' => $unit->id, 'guests' => 1, 'price_per_night' => 50]],
        ], $this->orgHeaders($admin, $org));

        $response->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('nights', 7)
            ->assertJsonPath('total_price', '350.00')
            ->assertJsonPath('units.0.id', $unit->id);
    }

    public function test_booking_requires_check_in_before_check_out(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property, 'customer' => $customer] = $this->scaffoldOrg();

        $this->postJson('/api/v1/bookings', [
            'property_id' => $property->id,
            'customer_id' => $customer->id,
            'check_in_date' => '2026-08-08',
            'check_out_date' => '2026-08-01',
            'guests' => 1,
        ], $this->orgHeaders($admin, $org))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['check_out_date']);
    }

    public function test_booking_calendar_returns_bookings_in_range(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property, 'unit' => $unit, 'customer' => $customer] = $this->scaffoldOrg();

        // Create a booking
        $this->postJson('/api/v1/bookings', [
            'property_id' => $property->id,
            'customer_id' => $customer->id,
            'check_in_date' => '2026-09-01',
            'check_out_date' => '2026-09-07',
            'guests' => 1,
            'total_price' => 300,
            'currency' => 'USD',
            'unit_ids' => [['unit_id' => $unit->id, 'guests' => 1, 'price_per_night' => 50]],
        ], $this->orgHeaders($admin, $org))->assertCreated();

        $this->getJson("/api/v1/bookings/calendar?property_id={$property->id}&month=2026-09", $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonCount(1, 'bookings');
    }

    public function test_booking_min_nights_rule_is_enforced(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property, 'unit' => $unit, 'customer' => $customer] = $this->scaffoldOrg();

        // Create a booking rule: min 3 nights
        \App\Models\BookingRule::create([
            'organizer_id' => $org->id,
            'property_id' => $property->id,
            'min_nights' => 3,
        ]);

        // 1 night — should fail
        $this->postJson('/api/v1/bookings', [
            'property_id' => $property->id,
            'customer_id' => $customer->id,
            'check_in_date' => '2026-10-01',
            'check_out_date' => '2026-10-02',
            'guests' => 1,
            'unit_ids' => [['unit_id' => $unit->id, 'guests' => 1]],
        ], $this->orgHeaders($admin, $org))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['check_out_date']);

        // 3 nights — should pass
        $this->postJson('/api/v1/bookings', [
            'property_id' => $property->id,
            'customer_id' => $customer->id,
            'check_in_date' => '2026-10-01',
            'check_out_date' => '2026-10-04',
            'guests' => 1,
            'total_price' => 150,
            'currency' => 'USD',
            'unit_ids' => [['unit_id' => $unit->id, 'guests' => 1, 'price_per_night' => 50]],
        ], $this->orgHeaders($admin, $org))
            ->assertCreated();
    }
}
