<?php

namespace Tests\Feature;

use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\BookingRule;
use App\Models\Customer;
use App\Models\Property;
use App\Models\RoomType;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    private function scaffoldProperty(): array
    {
        $org = $this->createOrganizer('avail-test');
        $admin = $this->createOrganizerAdmin($org);
        $property = Property::create([
            'organizer_id' => $org->id,
            'name' => 'Test Camp',
            'slug' => 'avail-test-camp',
            'type' => 'surf_camp',
            'country' => 'CR',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ]);
        return compact('org', 'admin', 'property');
    }

    private function createUnit(array $org_property, string $name = 'Room 1'): Unit
    {
        $rt = RoomType::create([
            'organizer_id' => $org_property['org']->id,
            'property_id' => $org_property['property']->id,
            'name' => 'Standard',
            'category' => 'private_room',
            'max_capacity' => 2,
        ]);
        return Unit::create([
            'organizer_id' => $org_property['org']->id,
            'property_id' => $org_property['property']->id,
            'room_type_id' => $rt->id,
            'name' => $name,
            'bed_category' => 'double',
            'capacity' => 2,
        ]);
    }

    // --- Availability rule CRUD ---

    public function test_create_availability_rule(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldProperty();

        $this->postJson('/api/v1/availability/rules', [
            'ruleable_type' => 'unit',
            'ruleable_id' => $property->id,
            'rule_type' => 'date_range',
            'start_date' => '2027-12-01',
            'end_date' => '2027-12-31',
            'capacity' => 0,
        ], $this->orgHeaders($admin, $org))
            ->assertCreated()
            ->assertJsonPath('rule_type', 'date_range')
            ->assertJsonPath('capacity', 0);
    }

    public function test_delete_availability_rule(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldProperty();

        $rule = AvailabilityRule::create([
            'organizer_id' => $org->id,
            'ruleable_type' => 'unit',
            'ruleable_id' => $property->id,
            'rule_type' => 'daily',
        ]);

        $this->deleteJson("/api/v1/availability/rules/{$rule->id}", [], $this->orgHeaders($admin, $org))
            ->assertNoContent();

        $this->assertDatabaseMissing('availability_rules', ['id' => $rule->id]);
    }

    // --- Calendar use case ---

    public function test_calendar_shows_no_availability_without_units(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldProperty();

        $this->getJson("/api/v1/availability?property_id={$property->id}&start_date=2028-01-01&end_date=2028-01-03", $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('total_units', 0)
            ->assertJsonPath('days.0.available_units', 0);
    }

    public function test_calendar_shows_unit_as_booked_on_occupied_days(): void
    {
        $scaffold = $this->scaffoldProperty();
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $scaffold;
        $unit = $this->createUnit($scaffold);

        $customer = Customer::create(['organizer_id' => $org->id, 'first_name' => 'T', 'last_name' => 'U', 'email' => 'cal@test.com']);
        $booking = Booking::create([
            'organizer_id' => $org->id, 'property_id' => $property->id,
            'customer_id' => $customer->id,
            'check_in_date' => '2028-02-01', 'check_out_date' => '2028-02-04',
            'nights' => 3, 'guests' => 1, 'status' => 'confirmed',
            'payment_status' => 'unpaid', 'total_price' => 150, 'currency' => 'USD', 'source' => 'direct',
        ]);
        $booking->units()->attach($unit->id, ['guests' => 1, 'price_per_night' => 50]);

        $response = $this->getJson(
            "/api/v1/availability?property_id={$property->id}&start_date=2028-02-01&end_date=2028-02-05",
            $this->orgHeaders($admin, $org)
        )->assertOk();

        // Feb 01–03: unit is booked, available=0
        $this->assertEquals(0, $response->json('days.0.available_units')); // Feb 01
        $this->assertEquals(0, $response->json('days.1.available_units')); // Feb 02
        $this->assertEquals(0, $response->json('days.2.available_units')); // Feb 03
        // Feb 04: checkout day — unit is free again
        $this->assertEquals(1, $response->json('days.3.available_units')); // Feb 04
    }

    public function test_calendar_counts_blocked_units_from_availability_rules(): void
    {
        $scaffold = $this->scaffoldProperty();
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $scaffold;
        $unit = $this->createUnit($scaffold);

        // Block the unit for March 2028
        AvailabilityRule::create([
            'organizer_id' => $org->id,
            'ruleable_type' => 'unit',
            'ruleable_id' => $unit->id,
            'rule_type' => 'date_range',
            'start_date' => '2028-03-01',
            'end_date' => '2028-03-31',
            'capacity' => 0,
        ]);

        $response = $this->getJson(
            "/api/v1/availability?property_id={$property->id}&start_date=2028-03-01&end_date=2028-03-02",
            $this->orgHeaders($admin, $org)
        )->assertOk();

        $this->assertEquals(0, $response->json('days.0.available_units'));
        $this->assertEquals(1, $response->json('days.0.blocked_units'));
    }

    // --- Check use case ---

    public function test_check_returns_available_when_no_bookings(): void
    {
        $scaffold = $this->scaffoldProperty();
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $scaffold;
        $this->createUnit($scaffold);

        $this->getJson(
            "/api/v1/availability/check?property_id={$property->id}&check_in_date=2028-04-01&check_out_date=2028-04-07",
            $this->orgHeaders($admin, $org)
        )->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('available_units', 1);
    }

    public function test_check_returns_unavailable_when_fully_booked(): void
    {
        $scaffold = $this->scaffoldProperty();
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $scaffold;
        $unit = $this->createUnit($scaffold);

        $customer = Customer::create(['organizer_id' => $org->id, 'first_name' => 'T', 'last_name' => 'U', 'email' => 'chk@test.com']);
        $booking = Booking::create([
            'organizer_id' => $org->id, 'property_id' => $property->id, 'customer_id' => $customer->id,
            'check_in_date' => '2028-05-01', 'check_out_date' => '2028-05-08',
            'nights' => 7, 'guests' => 1, 'status' => 'confirmed',
            'payment_status' => 'unpaid', 'total_price' => 350, 'currency' => 'USD', 'source' => 'direct',
        ]);
        $booking->units()->attach($unit->id, ['guests' => 1, 'price_per_night' => 50]);

        $this->getJson(
            "/api/v1/availability/check?property_id={$property->id}&check_in_date=2028-05-01&check_out_date=2028-05-08",
            $this->orgHeaders($admin, $org)
        )->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('available_units', 0);
    }

    public function test_check_min_units_threshold(): void
    {
        $scaffold = $this->scaffoldProperty();
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $scaffold;
        $this->createUnit($scaffold, 'Room A');
        $this->createUnit($scaffold, 'Room B');

        // Both free — requesting 2 min units should succeed
        $this->getJson(
            "/api/v1/availability/check?property_id={$property->id}&check_in_date=2028-06-01&check_out_date=2028-06-07&min_units=2",
            $this->orgHeaders($admin, $org)
        )->assertOk()->assertJsonPath('available', true);

        // Requesting 3 when only 2 exist — should fail
        $this->getJson(
            "/api/v1/availability/check?property_id={$property->id}&check_in_date=2028-06-01&check_out_date=2028-06-07&min_units=3",
            $this->orgHeaders($admin, $org)
        )->assertOk()->assertJsonPath('available', false);
    }

    // --- Booking rules ---

    public function test_create_booking_rule(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldProperty();

        $this->postJson('/api/v1/booking-rules', [
            'property_id' => $property->id,
            'min_nights' => 3,
            'max_nights' => 14,
            'check_in_days' => '1000001',
            'min_advance_days' => 7,
        ], $this->orgHeaders($admin, $org))
            ->assertCreated()
            ->assertJsonPath('min_nights', 3)
            ->assertJsonPath('min_advance_days', 7);
    }

    public function test_min_advance_days_enforced(): void
    {
        $scaffold = $this->scaffoldProperty();
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $scaffold;
        $unit = $this->createUnit($scaffold);
        $customer = Customer::create(['organizer_id' => $org->id, 'first_name' => 'T', 'last_name' => 'U', 'email' => 'adv@test.com']);

        BookingRule::create([
            'organizer_id' => $org->id,
            'property_id' => $property->id,
            'min_advance_days' => 30,
        ]);

        // Check-in tomorrow — violates 30-day advance rule
        $tomorrow = Carbon::tomorrow()->toDateString();
        $nextWeek = Carbon::tomorrow()->addDays(7)->toDateString();

        $this->postJson('/api/v1/bookings', [
            'property_id' => $property->id,
            'customer_id' => $customer->id,
            'check_in_date' => $tomorrow,
            'check_out_date' => $nextWeek,
            'guests' => 1,
            'unit_ids' => [['unit_id' => $unit->id, 'guests' => 1]],
        ], $this->orgHeaders($admin, $org))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['check_in_date']);
    }

    public function test_max_advance_days_enforced(): void
    {
        $scaffold = $this->scaffoldProperty();
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $scaffold;
        $unit = $this->createUnit($scaffold);
        $customer = Customer::create(['organizer_id' => $org->id, 'first_name' => 'T', 'last_name' => 'U', 'email' => 'maxadv@test.com']);

        BookingRule::create([
            'organizer_id' => $org->id,
            'property_id' => $property->id,
            'max_advance_days' => 60,
        ]);

        // Check-in in 90 days — violates max 60 days
        $checkIn = Carbon::today()->addDays(90)->toDateString();
        $checkOut = Carbon::today()->addDays(97)->toDateString();

        $this->postJson('/api/v1/bookings', [
            'property_id' => $property->id,
            'customer_id' => $customer->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guests' => 1,
            'unit_ids' => [['unit_id' => $unit->id, 'guests' => 1]],
        ], $this->orgHeaders($admin, $org))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['check_in_date']);
    }

    public function test_booking_rule_enforced_max_nights(): void
    {
        $scaffold = $this->scaffoldProperty();
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $scaffold;
        $unit = $this->createUnit($scaffold);
        $customer = Customer::create(['organizer_id' => $org->id, 'first_name' => 'T', 'last_name' => 'U', 'email' => 'maxn@test.com']);

        BookingRule::create(['organizer_id' => $org->id, 'property_id' => $property->id, 'max_nights' => 5]);

        $this->postJson('/api/v1/bookings', [
            'property_id' => $property->id,
            'customer_id' => $customer->id,
            'check_in_date' => '2029-01-01',
            'check_out_date' => '2029-01-08', // 7 nights
            'guests' => 1,
            'unit_ids' => [['unit_id' => $unit->id, 'guests' => 1]],
        ], $this->orgHeaders($admin, $org))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['check_out_date']);
    }

    public function test_tenant_isolation_booking_rules(): void
    {
        ['org' => $org, 'admin' => $admin] = $this->scaffoldProperty();
        $orgB = $this->createOrganizer('avail-org-b');
        $adminB = $this->createOrganizerAdmin($orgB);

        BookingRule::create(['organizer_id' => $org->id, 'min_nights' => 2]);

        $this->getJson('/api/v1/booking-rules', $this->orgHeaders($adminB, $orgB))
            ->assertOk()
            ->assertJsonPath('total', 0);
    }
}
