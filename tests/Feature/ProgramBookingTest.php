<?php

namespace Tests\Feature;

use App\Models\AddOn;
use App\Models\Customer;
use App\Models\Property;
use App\Models\RoomType;
use App\Models\Program;
use App\Models\Unit;
use Tests\TestCase;

class ProgramBookingTest extends TestCase
{
    private function scaffoldOrg(): array
    {
        $org = $this->createOrganizer('prog-booking-test');
        $admin = $this->createOrganizerAdmin($org);

        $property = Property::create([
            'organizer_id' => $org->id,
            'name' => 'Surf Camp',
            'slug' => 'prog-surf-camp',
            'type' => 'surf_camp',
            'country' => 'PT',
            'currency' => 'EUR',
            'timezone' => 'UTC',
        ]);

        $dormRoomType = RoomType::create([
            'organizer_id' => $org->id,
            'property_id' => $property->id,
            'name' => 'Surf Dormitory',
            'category' => 'dormitory',
            'is_active' => true,
        ]);

        $privateRoomType = RoomType::create([
            'organizer_id' => $org->id,
            'property_id' => $property->id,
            'name' => 'Private Double',
            'category' => 'private_room',
            'is_active' => true,
        ]);

        $dormUnit = Unit::create([
            'organizer_id' => $org->id,
            'property_id' => $property->id,
            'room_type_id' => $dormRoomType->id,
            'name' => 'Bed A1',
            'capacity' => 1,
            'is_active' => true,
        ]);

        $privateUnit = Unit::create([
            'organizer_id' => $org->id,
            'property_id' => $property->id,
            'room_type_id' => $privateRoomType->id,
            'name' => 'Room 1',
            'capacity' => 2,
            'is_active' => true,
        ]);

        $surfClass = AddOn::create([
            'organizer_id' => $org->id,
            'property_id' => $property->id,
            'name' => 'Surf Class',
            'category' => 'activity',
            'price' => 15.00,
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $transfer = AddOn::create([
            'organizer_id' => $org->id,
            'property_id' => $property->id,
            'name' => 'Airport Transfer',
            'category' => 'transport',
            'price' => 45.00,
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'organizer_id' => $org->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@test.com',
        ]);

        return compact('org', 'admin', 'property', 'dormRoomType', 'privateRoomType',
            'dormUnit', 'privateUnit', 'surfClass', 'transfer', 'customer');
    }

    public function test_program_booking_auto_assigns_unit_from_room_type(): void
    {
        $s = $this->scaffoldOrg();

        $program = Program::create([
            'organizer_id' => $s['org']->id,
            'property_id' => $s['property']->id,
            'room_type_id' => $s['dormRoomType']->id,
            'name' => '7-Day Surf Week',
            'type' => 'surf_camp',
            'duration_days' => 7,
            'base_price' => 450.00,
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/bookings', [
            'property_id' => $s['property']->id,
            'customer_id' => $s['customer']->id,
            'program_id' => $program->id,
            'check_in_date' => '2027-06-07',
            'check_out_date' => '2027-06-14',
            'guests' => 1,
        ], $this->orgHeaders($s['admin'], $s['org']));

        $response->assertCreated()
            ->assertJsonPath('program.id', $program->id)
            ->assertJsonPath('total_price', '450.00')
            ->assertJsonPath('currency', 'EUR')
            ->assertJsonPath('units.0.id', $s['dormUnit']->id);

        // Must not assign the private room unit
        $this->assertNotEquals($s['privateUnit']->id, $response->json('units.0.id'));
    }

    public function test_program_booking_auto_attaches_default_add_ons(): void
    {
        $s = $this->scaffoldOrg();

        $program = Program::create([
            'organizer_id' => $s['org']->id,
            'property_id' => $s['property']->id,
            'room_type_id' => $s['dormRoomType']->id,
            'name' => '7-Day Surf Week',
            'type' => 'surf_camp',
            'duration_days' => 7,
            'base_price' => 450.00,
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        // 7 surf classes included; airport transfer is optional (not default)
        $program->addOns()->attach($s['surfClass']->id, ['is_default' => true, 'quantity' => 7]);
        $program->addOns()->attach($s['transfer']->id, ['is_default' => false, 'quantity' => 1]);

        $response = $this->postJson('/api/v1/bookings', [
            'property_id' => $s['property']->id,
            'customer_id' => $s['customer']->id,
            'program_id' => $program->id,
            'check_in_date' => '2027-07-06',
            'check_out_date' => '2027-07-13',
            'guests' => 1,
        ], $this->orgHeaders($s['admin'], $s['org']));

        $response->assertCreated();

        // Only the default add-on (surf class) should be auto-attached
        $addOns = $response->json('add_ons');
        $this->assertCount(1, $addOns);
        $this->assertEquals($s['surfClass']->id, $addOns[0]['id']);
        $this->assertEquals(7, $addOns[0]['pivot']['quantity']);
    }

    public function test_explicit_add_on_in_request_overrides_program_default(): void
    {
        $s = $this->scaffoldOrg();

        $program = Program::create([
            'organizer_id' => $s['org']->id,
            'property_id' => $s['property']->id,
            'room_type_id' => $s['dormRoomType']->id,
            'name' => '7-Day Surf Week',
            'type' => 'surf_camp',
            'duration_days' => 7,
            'base_price' => 450.00,
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $program->addOns()->attach($s['surfClass']->id, ['is_default' => true, 'quantity' => 7]);

        // Request overrides quantity to 5 and custom price
        $response = $this->postJson('/api/v1/bookings', [
            'property_id' => $s['property']->id,
            'customer_id' => $s['customer']->id,
            'program_id' => $program->id,
            'check_in_date' => '2027-08-03',
            'check_out_date' => '2027-08-10',
            'guests' => 1,
            'add_on_ids' => [
                ['add_on_id' => $s['surfClass']->id, 'quantity' => 5, 'unit_price' => 10],
            ],
        ], $this->orgHeaders($s['admin'], $s['org']));

        $response->assertCreated();

        $addOns = $response->json('add_ons');
        $this->assertCount(1, $addOns);
        $this->assertEquals(5, $addOns[0]['pivot']['quantity']);
        $this->assertEquals('10.00', $addOns[0]['pivot']['unit_price']);
    }

    public function test_program_booking_uses_explicit_total_price_over_base_price(): void
    {
        $s = $this->scaffoldOrg();

        $program = Program::create([
            'organizer_id' => $s['org']->id,
            'property_id' => $s['property']->id,
            'room_type_id' => $s['dormRoomType']->id,
            'name' => '7-Day Surf Week',
            'type' => 'surf_camp',
            'duration_days' => 7,
            'base_price' => 450.00,
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        // Explicit total_price (e.g. after discount) should take precedence
        $response = $this->postJson('/api/v1/bookings', [
            'property_id' => $s['property']->id,
            'customer_id' => $s['customer']->id,
            'program_id' => $program->id,
            'check_in_date' => '2027-09-06',
            'check_out_date' => '2027-09-13',
            'guests' => 1,
            'total_price' => 400.00,
            'currency' => 'EUR',
        ], $this->orgHeaders($s['admin'], $s['org']));

        $response->assertCreated()
            ->assertJsonPath('total_price', '400.00');
    }

    public function test_program_booking_fails_when_no_units_available_in_room_type(): void
    {
        $s = $this->scaffoldOrg();

        $program = Program::create([
            'organizer_id' => $s['org']->id,
            'property_id' => $s['property']->id,
            'room_type_id' => $s['dormRoomType']->id,
            'name' => '7-Day Surf Week',
            'type' => 'surf_camp',
            'duration_days' => 7,
            'base_price' => 450.00,
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        // Fill the only dorm unit
        $this->postJson('/api/v1/bookings', [
            'property_id' => $s['property']->id,
            'customer_id' => $s['customer']->id,
            'program_id' => $program->id,
            'check_in_date' => '2027-10-03',
            'check_out_date' => '2027-10-10',
            'guests' => 1,
        ], $this->orgHeaders($s['admin'], $s['org']))->assertCreated();

        // Second booking on overlapping dates — dorm is full, private room is in a different room type
        $this->postJson('/api/v1/bookings', [
            'property_id' => $s['property']->id,
            'customer_id' => $s['customer']->id,
            'program_id' => $program->id,
            'check_in_date' => '2027-10-05',
            'check_out_date' => '2027-10-12',
            'guests' => 1,
        ], $this->orgHeaders($s['admin'], $s['org']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.program_id.0', 'Not enough capacity available: can accommodate 0 of 1 guests on the requested dates.');
    }

    public function test_program_booking_auto_assigns_multiple_units_for_group(): void
    {
        $s = $this->scaffoldOrg();

        $bed2 = Unit::create([
            'organizer_id' => $s['org']->id,
            'property_id' => $s['property']->id,
            'room_type_id' => $s['dormRoomType']->id,
            'name' => 'Bed A2',
            'capacity' => 1,
            'is_active' => true,
        ]);

        $program = Program::create([
            'organizer_id' => $s['org']->id,
            'property_id' => $s['property']->id,
            'room_type_id' => $s['dormRoomType']->id,
            'name' => '7-Day Surf Week',
            'type' => 'surf_camp',
            'duration_days' => 7,
            'base_price' => 450.00,
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/bookings', [
            'property_id' => $s['property']->id,
            'customer_id' => $s['customer']->id,
            'program_id' => $program->id,
            'check_in_date' => '2027-06-07',
            'check_out_date' => '2027-06-14',
            'guests' => 2,
        ], $this->orgHeaders($s['admin'], $s['org']));

        $response->assertCreated();
        $this->assertCount(2, $response->json('units'));

        $bookedUnitIds = collect($response->json('units'))->pluck('id');
        $this->assertContains($s['dormUnit']->id, $bookedUnitIds);
        $this->assertContains($bed2->id, $bookedUnitIds);
    }

    public function test_program_booking_fails_with_capacity_message_when_not_enough_units(): void
    {
        $s = $this->scaffoldOrg();

        $program = Program::create([
            'organizer_id' => $s['org']->id,
            'property_id' => $s['property']->id,
            'room_type_id' => $s['dormRoomType']->id,
            'name' => '7-Day Surf Week',
            'type' => 'surf_camp',
            'duration_days' => 7,
            'base_price' => 450.00,
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        // Only 1 dorm bed (capacity=1) but requesting 2 guests
        $this->postJson('/api/v1/bookings', [
            'property_id' => $s['property']->id,
            'customer_id' => $s['customer']->id,
            'program_id' => $program->id,
            'check_in_date' => '2027-06-07',
            'check_out_date' => '2027-06-14',
            'guests' => 2,
        ], $this->orgHeaders($s['admin'], $s['org']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.program_id.0', 'Not enough capacity available: can accommodate 1 of 2 guests on the requested dates.');
    }

    public function test_direct_booking_fails_when_unit_capacity_insufficient(): void
    {
        $s = $this->scaffoldOrg();

        // dormUnit has capacity=1, but booking is for 2 guests
        $this->postJson('/api/v1/bookings', [
            'property_id' => $s['property']->id,
            'customer_id' => $s['customer']->id,
            'check_in_date' => '2027-06-07',
            'check_out_date' => '2027-06-14',
            'guests' => 2,
            'unit_ids' => [['unit_id' => $s['dormUnit']->id]],
        ], $this->orgHeaders($s['admin'], $s['org']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.unit_ids.0', 'Selected unit(s) have a combined capacity of 1 but 2 guests were requested.');
    }

    public function test_direct_booking_still_requires_unit_ids_without_program(): void
    {
        $s = $this->scaffoldOrg();

        $this->postJson('/api/v1/bookings', [
            'property_id' => $s['property']->id,
            'customer_id' => $s['customer']->id,
            'check_in_date' => '2027-11-01',
            'check_out_date' => '2027-11-08',
            'guests' => 1,
        ], $this->orgHeaders($s['admin'], $s['org']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unit_ids']);
    }

    public function test_program_booking_price_scales_with_guest_count(): void
    {
        $s = $this->scaffoldOrg();

        // Second bed so 2 guests can be accommodated
        Unit::create([
            'organizer_id' => $s['org']->id,
            'property_id' => $s['property']->id,
            'room_type_id' => $s['dormRoomType']->id,
            'name' => 'Bed A2',
            'capacity' => 1,
            'is_active' => true,
        ]);

        $program = Program::create([
            'organizer_id' => $s['org']->id,
            'property_id' => $s['property']->id,
            'room_type_id' => $s['dormRoomType']->id,
            'name' => '7-Day Surf Week',
            'type' => 'surf_camp',
            'duration_days' => 7,
            'base_price' => 450.00,
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/bookings', [
            'property_id' => $s['property']->id,
            'customer_id' => $s['customer']->id,
            'program_id' => $program->id,
            'check_in_date' => '2027-06-07',
            'check_out_date' => '2027-06-14',
            'guests' => 2,
        ], $this->orgHeaders($s['admin'], $s['org']));

        $response->assertCreated()
            ->assertJsonPath('total_price', '900.00')  // 450 × 2 guests
            ->assertJsonPath('currency', 'EUR')
            ->assertJsonPath('guests', 2);
    }

    public function test_program_without_room_type_auto_assigns_any_available_unit(): void
    {
        $s = $this->scaffoldOrg();

        // Program with no room_type_id — falls back to any unit in the property
        $program = Program::create([
            'organizer_id' => $s['org']->id,
            'property_id' => $s['property']->id,
            'name' => 'Flexible Stay',
            'type' => 'surf_camp',
            'duration_days' => 7,
            'base_price' => 350.00,
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/bookings', [
            'property_id' => $s['property']->id,
            'customer_id' => $s['customer']->id,
            'program_id' => $program->id,
            'check_in_date' => '2027-12-05',
            'check_out_date' => '2027-12-12',
            'guests' => 1,
        ], $this->orgHeaders($s['admin'], $s['org']));

        $response->assertCreated();
        $this->assertNotEmpty($response->json('units'));
    }
}
