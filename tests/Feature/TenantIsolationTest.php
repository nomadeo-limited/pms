<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Organizer;
use App\Models\Property;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    public function test_organizer_cannot_see_other_organizers_bookings(): void
    {
        $orgA = $this->createOrganizer('org-a');
        $orgB = $this->createOrganizer('org-b');
        $adminA = $this->createOrganizerAdmin($orgA);
        $adminB = $this->createOrganizerAdmin($orgB);

        // Seed a booking under Org A directly
        $propA = Property::create([
            'organizer_id' => $orgA->id,
            'name' => 'Prop A',
            'slug' => 'prop-a',
            'type' => 'surf_camp',
            'country' => 'US',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ]);
        $customerA = Customer::create([
            'organizer_id' => $orgA->id,
            'first_name' => 'Alice',
            'last_name' => 'A',
            'email' => 'alice@a.com',
        ]);
        Booking::create([
            'organizer_id' => $orgA->id,
            'property_id' => $propA->id,
            'customer_id' => $customerA->id,
            'check_in_date' => '2026-07-01',
            'check_out_date' => '2026-07-07',
            'nights' => 6,
            'guests' => 1,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_price' => 300,
            'currency' => 'USD',
            'source' => 'direct',
        ]);

        // Org A admin sees 1 booking
        $this->getJson('/api/v1/bookings', $this->orgHeaders($adminA, $orgA))
            ->assertOk()
            ->assertJsonPath('total', 1);

        // Org B admin sees 0 bookings
        $this->getJson('/api/v1/bookings', $this->orgHeaders($adminB, $orgB))
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_organizer_cannot_see_other_organizers_customers(): void
    {
        $orgA = $this->createOrganizer('cust-org-a');
        $orgB = $this->createOrganizer('cust-org-b');
        $adminA = $this->createOrganizerAdmin($orgA);
        $adminB = $this->createOrganizerAdmin($orgB);

        Customer::create([
            'organizer_id' => $orgA->id,
            'first_name' => 'Alice',
            'last_name' => 'A',
            'email' => 'alice2@a.com',
        ]);

        $this->getJson('/api/v1/customers', $this->orgHeaders($adminA, $orgA))
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->getJson('/api/v1/customers', $this->orgHeaders($adminB, $orgB))
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_super_admin_can_access_any_organizer_with_header(): void
    {
        $orgA = $this->createOrganizer('super-org-a');
        $superAdmin = $this->createSuperAdmin();

        $propA = Property::create([
            'organizer_id' => $orgA->id,
            'name' => 'Prop SA',
            'slug' => 'prop-sa',
            'type' => 'surf_camp',
            'country' => 'US',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ]);

        $token = auth('api')->login($superAdmin);
        $this->getJson('/api/v1/properties', [
            'Authorization' => "Bearer {$token}",
            'X-Organizer-ID' => $orgA->id,
            'Accept' => 'application/json',
        ])->assertOk()->assertJsonPath('total', 1);
    }
}
