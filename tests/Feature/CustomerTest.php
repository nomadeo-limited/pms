<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Property;
use App\Models\Review;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    private function scaffoldOrg(): array
    {
        $org = $this->createOrganizer('cust-mgmt');
        $admin = $this->createOrganizerAdmin($org);
        return compact('org', 'admin');
    }

    public function test_create_customer(): void
    {
        ['org' => $org, 'admin' => $admin] = $this->scaffoldOrg();

        $this->postJson('/api/v1/customers', [
            'first_name' => 'Alice',
            'last_name' => 'Surfer',
            'email' => 'alice@surfer.com',
            'nationality' => 'PT',
            'phone' => '+351912345678',
        ], $this->orgHeaders($admin, $org))
            ->assertCreated()
            ->assertJsonPath('first_name', 'Alice')
            ->assertJsonPath('nationality', 'PT');
    }

    public function test_email_unique_per_organizer(): void
    {
        ['org' => $org, 'admin' => $admin] = $this->scaffoldOrg();

        Customer::create(['organizer_id' => $org->id, 'first_name' => 'Alice', 'last_name' => 'A', 'email' => 'dup@test.com']);

        $this->postJson('/api/v1/customers', [
            'first_name' => 'Alice2', 'last_name' => 'B', 'email' => 'dup@test.com',
        ], $this->orgHeaders($admin, $org))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_same_email_allowed_across_organizers(): void
    {
        ['org' => $org, 'admin' => $admin] = $this->scaffoldOrg();
        $orgB = $this->createOrganizer('cust-org-b2');
        $adminB = $this->createOrganizerAdmin($orgB);

        Customer::create(['organizer_id' => $org->id, 'first_name' => 'Alice', 'last_name' => 'A', 'email' => 'shared@test.com']);

        $this->postJson('/api/v1/customers', [
            'first_name' => 'Alice', 'last_name' => 'B', 'email' => 'shared@test.com',
        ], $this->orgHeaders($adminB, $orgB))
            ->assertCreated();
    }

    public function test_search_customers(): void
    {
        ['org' => $org, 'admin' => $admin] = $this->scaffoldOrg();

        Customer::create(['organizer_id' => $org->id, 'first_name' => 'Bob', 'last_name' => 'Marley', 'email' => 'bob@test.com']);
        Customer::create(['organizer_id' => $org->id, 'first_name' => 'Carol', 'last_name' => 'King', 'email' => 'carol@test.com']);

        $this->getJson('/api/v1/customers?search=bob', $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.first_name', 'Bob');
    }

    public function test_update_customer(): void
    {
        ['org' => $org, 'admin' => $admin] = $this->scaffoldOrg();

        $customer = Customer::create(['organizer_id' => $org->id, 'first_name' => 'Old', 'last_name' => 'Name', 'email' => 'old@test.com']);

        $this->putJson("/api/v1/customers/{$customer->id}", [
            'first_name' => 'New',
            'notes' => 'VIP guest',
        ], $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('first_name', 'New');
    }

    public function test_delete_customer(): void
    {
        ['org' => $org, 'admin' => $admin] = $this->scaffoldOrg();

        $customer = Customer::create(['organizer_id' => $org->id, 'first_name' => 'Del', 'last_name' => 'Me', 'email' => 'del@test.com']);

        $this->deleteJson("/api/v1/customers/{$customer->id}", [], $this->orgHeaders($admin, $org))
            ->assertNoContent();

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    // --- Reviews ---

    public function test_create_review(): void
    {
        ['org' => $org, 'admin' => $admin] = $this->scaffoldOrg();

        $property = Property::create(['organizer_id' => $org->id, 'name' => 'Camp', 'slug' => 'review-camp', 'type' => 'surf_camp', 'country' => 'CR', 'currency' => 'USD', 'timezone' => 'UTC']);
        $customer = Customer::create(['organizer_id' => $org->id, 'first_name' => 'Jane', 'last_name' => 'D', 'email' => 'review@test.com']);
        $booking = Booking::create([
            'organizer_id' => $org->id, 'property_id' => $property->id, 'customer_id' => $customer->id,
            'check_in_date' => '2026-01-01', 'check_out_date' => '2026-01-07',
            'nights' => 6, 'guests' => 1, 'status' => 'completed',
            'payment_status' => 'paid', 'total_price' => 300, 'currency' => 'USD', 'source' => 'direct',
        ]);

        $this->postJson('/api/v1/reviews', [
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'overall_rating' => 5,
            'comment' => 'Amazing experience!',
        ], $this->orgHeaders($admin, $org))
            ->assertCreated()
            ->assertJsonPath('overall_rating', 5)
            ->assertJsonPath('comment', 'Amazing experience!');
    }

    public function test_publish_review(): void
    {
        ['org' => $org, 'admin' => $admin] = $this->scaffoldOrg();

        $property = Property::create(['organizer_id' => $org->id, 'name' => 'Camp2', 'slug' => 'pub-camp', 'type' => 'surf_camp', 'country' => 'CR', 'currency' => 'USD', 'timezone' => 'UTC']);
        $customer = Customer::create(['organizer_id' => $org->id, 'first_name' => 'Jake', 'last_name' => 'D', 'email' => 'pub@test.com']);
        $booking = Booking::create(['organizer_id' => $org->id, 'property_id' => $property->id, 'customer_id' => $customer->id, 'check_in_date' => '2026-02-01', 'check_out_date' => '2026-02-07', 'nights' => 6, 'guests' => 1, 'status' => 'completed', 'payment_status' => 'paid', 'total_price' => 300, 'currency' => 'USD', 'source' => 'direct']);

        $review = Review::create(['organizer_id' => $org->id, 'booking_id' => $booking->id, 'customer_id' => $customer->id, 'overall_rating' => 4, 'is_published' => false]);

        $this->putJson("/api/v1/reviews/{$review->id}", ['is_published' => true], $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('is_published', true);
    }
}
