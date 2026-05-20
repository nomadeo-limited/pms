<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Property;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    private function scaffoldOrgWithBookings(): array
    {
        $org = $this->createOrganizer('report-test');
        $admin = $this->createOrganizerAdmin($org);
        $property = Property::create(['organizer_id' => $org->id, 'name' => 'Camp', 'slug' => 'report-camp', 'type' => 'surf_camp', 'country' => 'CR', 'currency' => 'USD', 'timezone' => 'UTC']);
        $customer = Customer::create(['organizer_id' => $org->id, 'first_name' => 'R', 'last_name' => 'T', 'email' => 'rt@test.com', 'nationality' => 'US']);

        $booking = Booking::create(['organizer_id' => $org->id, 'property_id' => $property->id, 'customer_id' => $customer->id, 'check_in_date' => '2027-04-01', 'check_out_date' => '2027-04-08', 'nights' => 7, 'guests' => 2, 'status' => 'confirmed', 'payment_status' => 'paid', 'total_price' => 700, 'currency' => 'USD', 'source' => 'direct']);

        Payment::create(['organizer_id' => $org->id, 'booking_id' => $booking->id, 'amount' => 700, 'currency' => 'USD', 'method' => 'stripe', 'status' => 'completed', 'paid_at' => '2027-04-01 10:00:00']);

        return compact('org', 'admin', 'property', 'booking');
    }

    public function test_occupancy_report(): void
    {
        ['org' => $org, 'admin' => $admin] = $this->scaffoldOrgWithBookings();

        $this->getJson('/api/v1/reports/occupancy?start_date=2027-04-01&end_date=2027-04-30', $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('total_bookings', 1)
            ->assertJsonPath('total_nights', 7)
            ->assertJsonPath('total_guests', 2);
    }

    public function test_occupancy_filtered_by_property(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldOrgWithBookings();

        $otherProperty = Property::create(['organizer_id' => $org->id, 'name' => 'Other', 'slug' => 'report-other', 'type' => 'surf_camp', 'country' => 'CR', 'currency' => 'USD', 'timezone' => 'UTC']);

        // Filter to the property that has the booking
        $this->getJson("/api/v1/reports/occupancy?start_date=2027-04-01&end_date=2027-04-30&property_id={$property->id}", $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('total_bookings', 1);

        // Filter to other property — no bookings
        $this->getJson("/api/v1/reports/occupancy?start_date=2027-04-01&end_date=2027-04-30&property_id={$otherProperty->id}", $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('total_bookings', 0);
    }

    public function test_revenue_report(): void
    {
        ['org' => $org, 'admin' => $admin] = $this->scaffoldOrgWithBookings();

        $this->getJson('/api/v1/reports/revenue?start_date=2027-04-01&end_date=2027-04-30', $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('total_revenue', 700);
    }

    public function test_booking_stats(): void
    {
        ['org' => $org, 'admin' => $admin] = $this->scaffoldOrgWithBookings();

        $year = now()->year;
        $this->getJson("/api/v1/reports/bookings?start_date={$year}-01-01&end_date={$year}-12-31", $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('average_nights', 7);
    }

    public function test_customer_stats(): void
    {
        ['org' => $org, 'admin' => $admin] = $this->scaffoldOrgWithBookings();

        $year = now()->year;
        $this->getJson("/api/v1/reports/customers?start_date={$year}-01-01&end_date={$year}-12-31", $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('total_customers', 1)
            ->assertJsonPath('new_customers', 1);
    }

    public function test_report_requires_date_range(): void
    {
        ['org' => $org, 'admin' => $admin] = $this->scaffoldOrgWithBookings();

        $this->getJson('/api/v1/reports/occupancy', $this->orgHeaders($admin, $org))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date', 'end_date']);
    }

    public function test_report_tenant_isolation(): void
    {
        ['org' => $org, 'admin' => $admin] = $this->scaffoldOrgWithBookings();
        $orgB = $this->createOrganizer('report-org-b');
        $adminB = $this->createOrganizerAdmin($orgB);

        // Org B should see 0 bookings despite org A having data
        $this->getJson('/api/v1/reports/occupancy?start_date=2027-04-01&end_date=2027-04-30', $this->orgHeaders($adminB, $orgB))
            ->assertOk()
            ->assertJsonPath('total_bookings', 0);
    }
}
