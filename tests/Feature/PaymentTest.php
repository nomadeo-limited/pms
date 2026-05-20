<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Property;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    private function scaffoldBooking(): array
    {
        $org = $this->createOrganizer('pay-test');
        $admin = $this->createOrganizerAdmin($org);
        $property = Property::create(['organizer_id' => $org->id, 'name' => 'Camp', 'slug' => 'pay-camp', 'type' => 'surf_camp', 'country' => 'CR', 'currency' => 'USD', 'timezone' => 'UTC']);
        $customer = Customer::create(['organizer_id' => $org->id, 'first_name' => 'Pay', 'last_name' => 'User', 'email' => 'pay@test.com']);
        $booking = Booking::create([
            'organizer_id' => $org->id,
            'property_id' => $property->id,
            'customer_id' => $customer->id,
            'check_in_date' => '2027-03-01',
            'check_out_date' => '2027-03-08',
            'nights' => 7,
            'guests' => 1,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_price' => 700.00,
            'currency' => 'USD',
            'source' => 'direct',
        ]);
        return compact('org', 'admin', 'booking');
    }

    public function test_record_payment_sets_status_to_partial(): void
    {
        ['org' => $org, 'admin' => $admin, 'booking' => $booking] = $this->scaffoldBooking();

        $this->postJson("/api/v1/bookings/{$booking->id}/payments", [
            'amount' => 350.00,
            'currency' => 'USD',
            'method' => 'bank_transfer',
        ], $this->orgHeaders($admin, $org))
            ->assertCreated()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('amount', '350.00');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'payment_status' => 'partial',
        ]);
    }

    public function test_full_payment_sets_status_to_paid(): void
    {
        ['org' => $org, 'admin' => $admin, 'booking' => $booking] = $this->scaffoldBooking();

        $this->postJson("/api/v1/bookings/{$booking->id}/payments", [
            'amount' => 700.00,
            'currency' => 'USD',
            'method' => 'stripe',
        ], $this->orgHeaders($admin, $org))->assertCreated();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'payment_status' => 'paid']);
    }

    public function test_list_payments_for_booking(): void
    {
        ['org' => $org, 'admin' => $admin, 'booking' => $booking] = $this->scaffoldBooking();

        Payment::create(['organizer_id' => $org->id, 'booking_id' => $booking->id, 'amount' => 200, 'currency' => 'USD', 'method' => 'cash', 'status' => 'completed', 'paid_at' => now()]);

        $this->getJson("/api/v1/bookings/{$booking->id}/payments", $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_payment_for_unknown_booking_returns_404(): void
    {
        ['org' => $org, 'admin' => $admin] = $this->scaffoldBooking();

        $this->postJson('/api/v1/bookings/00000000-0000-0000-0000-000000000000/payments', [
            'amount' => 100, 'currency' => 'USD', 'method' => 'cash',
        ], $this->orgHeaders($admin, $org))
            ->assertNotFound();
    }

    public function test_paid_at_auto_set_for_completed_payment(): void
    {
        ['org' => $org, 'admin' => $admin, 'booking' => $booking] = $this->scaffoldBooking();

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/payments", [
            'amount' => 100, 'currency' => 'USD', 'method' => 'cash',
        ], $this->orgHeaders($admin, $org))->assertCreated();

        $this->assertNotNull($response->json('paid_at'));
    }
}
