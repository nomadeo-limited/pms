<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Property;
use App\Models\RoomType;
use App\Models\Unit;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    private function scaffoldProperty(): array
    {
        $org = $this->createOrganizer('inv-test');
        $admin = $this->createOrganizerAdmin($org);
        $property = Property::create([
            'organizer_id' => $org->id,
            'name' => 'Surf Camp',
            'slug' => 'surf-camp-inv',
            'type' => 'surf_camp',
            'country' => 'CR',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ]);
        return compact('org', 'admin', 'property');
    }

    public function test_create_room_type(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldProperty();

        $this->postJson('/api/v1/room-types', [
            'property_id' => $property->id,
            'name' => 'Ocean Dorm',
            'category' => 'shared_dorm',
            'max_capacity' => 8,
        ], $this->orgHeaders($admin, $org))
            ->assertCreated()
            ->assertJsonPath('name', 'Ocean Dorm')
            ->assertJsonPath('category', 'shared_dorm');
    }

    public function test_list_room_types_requires_property_id(): void
    {
        ['org' => $org, 'admin' => $admin] = $this->scaffoldProperty();

        $this->getJson('/api/v1/room-types', $this->orgHeaders($admin, $org))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['property_id']);
    }

    public function test_create_unit_under_room_type(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldProperty();

        $roomType = RoomType::create([
            'organizer_id' => $org->id,
            'property_id' => $property->id,
            'name' => 'Dorm A',
            'category' => 'shared_dorm',
            'max_capacity' => 6,
        ]);

        $this->postJson('/api/v1/units', [
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'name' => 'Bed 1',
            'bed_category' => 'single',
            'capacity' => 1,
        ], $this->orgHeaders($admin, $org))
            ->assertCreated()
            ->assertJsonPath('name', 'Bed 1')
            ->assertJsonPath('bed_category', 'single');
    }

    public function test_availability_shows_all_units_when_no_bookings(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldProperty();

        $roomType = RoomType::create([
            'organizer_id' => $org->id,
            'property_id' => $property->id,
            'name' => 'Standard',
            'category' => 'private_room',
            'max_capacity' => 2,
        ]);

        Unit::create(['organizer_id' => $org->id, 'property_id' => $property->id, 'room_type_id' => $roomType->id, 'name' => 'Room 1', 'bed_category' => 'queen', 'capacity' => 2]);
        Unit::create(['organizer_id' => $org->id, 'property_id' => $property->id, 'room_type_id' => $roomType->id, 'name' => 'Room 2', 'bed_category' => 'queen', 'capacity' => 2]);

        $this->getJson("/api/v1/units/availability?property_id={$property->id}&check_in_date=2026-12-01&check_out_date=2026-12-07", $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('total_available_units', 2)
            ->assertJsonCount(1, 'room_types')
            ->assertJsonPath('room_types.0.available_count', 2);
    }

    public function test_availability_excludes_booked_units(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldProperty();

        $roomType = RoomType::create([
            'organizer_id' => $org->id,
            'property_id' => $property->id,
            'name' => 'Standard',
            'category' => 'private_room',
            'max_capacity' => 2,
        ]);

        $unit1 = Unit::create(['organizer_id' => $org->id, 'property_id' => $property->id, 'room_type_id' => $roomType->id, 'name' => 'Room 1', 'bed_category' => 'queen', 'capacity' => 2]);
        $unit2 = Unit::create(['organizer_id' => $org->id, 'property_id' => $property->id, 'room_type_id' => $roomType->id, 'name' => 'Room 2', 'bed_category' => 'queen', 'capacity' => 2]);

        $customer = Customer::create(['organizer_id' => $org->id, 'first_name' => 'Test', 'last_name' => 'User', 'email' => 'test@inv.com']);

        // Book unit1 for Dec 01-07
        $booking = Booking::create([
            'organizer_id' => $org->id,
            'property_id' => $property->id,
            'customer_id' => $customer->id,
            'check_in_date' => '2026-12-01',
            'check_out_date' => '2026-12-07',
            'nights' => 6,
            'guests' => 2,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_price' => 300,
            'currency' => 'USD',
            'source' => 'direct',
        ]);
        $booking->units()->attach($unit1->id, ['guests' => 2, 'price_per_night' => 50]);

        // Overlapping range: only unit2 should be available
        $this->getJson("/api/v1/units/availability?property_id={$property->id}&check_in_date=2026-12-03&check_out_date=2026-12-05", $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('total_available_units', 1)
            ->assertJsonPath('room_types.0.available_units.0.id', $unit2->id);
    }

    public function test_availability_includes_unit_when_booking_is_cancelled(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldProperty();

        $roomType = RoomType::create([
            'organizer_id' => $org->id,
            'property_id' => $property->id,
            'name' => 'Standard',
            'category' => 'private_room',
            'max_capacity' => 2,
        ]);

        $unit = Unit::create(['organizer_id' => $org->id, 'property_id' => $property->id, 'room_type_id' => $roomType->id, 'name' => 'Room 1', 'bed_category' => 'double', 'capacity' => 2]);
        $customer = Customer::create(['organizer_id' => $org->id, 'first_name' => 'Test', 'last_name' => 'User', 'email' => 'test2@inv.com']);

        $booking = Booking::create([
            'organizer_id' => $org->id,
            'property_id' => $property->id,
            'customer_id' => $customer->id,
            'check_in_date' => '2026-12-10',
            'check_out_date' => '2026-12-15',
            'nights' => 5,
            'guests' => 1,
            'status' => 'cancelled', // cancelled — unit should still be available
            'payment_status' => 'unpaid',
            'total_price' => 250,
            'currency' => 'USD',
            'source' => 'direct',
        ]);
        $booking->units()->attach($unit->id, ['guests' => 1, 'price_per_night' => 50]);

        $this->getJson("/api/v1/units/availability?property_id={$property->id}&check_in_date=2026-12-10&check_out_date=2026-12-15", $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('total_available_units', 1);
    }
}
