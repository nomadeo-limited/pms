<?php

namespace Database\Seeders;

use App\Models\AddOn;
use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\BookingRule;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\Organizer;
use App\Models\Payment;
use App\Models\PaymentRule;
use App\Models\PricingRule;
use App\Models\Program;
use App\Models\Property;
use App\Models\RoomType;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ── Organizer ──────────────────────────────────────────────────────────
        $organizer = Organizer::firstOrCreate(['slug' => 'oceans-surf-camp'], [
            'name' => "Ocean's Surf Camp",
            'email' => 'hello@test.com',
            'phone' => '+351 912 345 678',
            'address' => 'Rua do Mar 42',
            'city' => 'Ericeira',
            'country' => 'PT',
            'currency' => 'EUR',
            'timezone' => 'Europe/Lisbon',
            'locale' => 'en',
            'short_description' => 'Portugal\'s finest surf camp since 2010.',
            'description' => 'Ocean\'s Surf Camp sits on the cliffs above Ericeira, UNESCO World Surfing Reserve. We run 7- and 14-day programs for all levels, with daily lessons, video analysis, and yoga.',
            'is_active' => true,
        ]);

        // ── Staff ──────────────────────────────────────────────────────────────
        $adminUser = User::firstOrCreate(['email' => 'manager@test.com'], [
            'name' => 'Surf Manager',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);
        $adminUser->assignRole('organizer_admin');
        $organizer->users()->syncWithoutDetaching([$adminUser->id]);

        $staffUser = User::firstOrCreate(['email' => 'staff@test.com'], [
            'name' => 'Surf Staff',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);
        $staffUser->assignRole('organizer_staff');
        $organizer->users()->syncWithoutDetaching([$staffUser->id]);

        // ── Property ───────────────────────────────────────────────────────────
        $property = Property::firstOrCreate(
            ['organizer_id' => $organizer->id, 'slug' => 'oceans-main-house'],
            [
                'name' => 'Main House',
                'type' => 'surf_camp',
                'address' => 'Rua do Mar 42',
                'city' => 'Ericeira',
                'country' => 'PT',
                'timezone' => 'Europe/Lisbon',
                'currency' => 'EUR',
                'locale' => 'en',
                'latitude' => 38.9634,
                'longitude' => -9.4165,
                'description' => 'Our main surf house, 2 minutes walk from Ribeira d\'Ilhas beach.',
                'amenities' => ['wifi', 'surf_storage', 'wetsuit_rinse', 'outdoor_shower', 'bbq', 'yoga_deck'],
                'is_active' => true,
            ]
        );

        // ── Room Types ─────────────────────────────────────────────────────────
        $dormRoomType = RoomType::firstOrCreate(
            ['organizer_id' => $organizer->id, 'property_id' => $property->id, 'name' => 'Surf Dormitory'],
            [
                'category' => 'dormitory',
                'description' => '6-bed mixed dormitory with en-suite bathroom and surf storage.',
                'max_capacity' => 6,
                'amenities' => ['en_suite_bathroom', 'lockers', 'air_conditioning', 'fans'],
                'is_active' => true,
            ]
        );

        $privateRoomType = RoomType::firstOrCreate(
            ['organizer_id' => $organizer->id, 'property_id' => $property->id, 'name' => 'Private Double'],
            [
                'category' => 'private_room',
                'description' => 'Private double room with sea view balcony.',
                'max_capacity' => 2,
                'amenities' => ['sea_view', 'balcony', 'en_suite_bathroom', 'air_conditioning'],
                'is_active' => true,
            ]
        );

        // ── Units ──────────────────────────────────────────────────────────────
        $dormUnits = [];
        foreach (range(1, 6) as $n) {
            $dormUnits[] = Unit::firstOrCreate(
                ['organizer_id' => $organizer->id, 'property_id' => $property->id, 'name' => "Dorm Bed {$n}"],
                [
                    'room_type_id' => $dormRoomType->id,
                    'bed_category' => 'bunk',
                    'capacity' => 1,
                    'is_active' => true,
                ]
            );
        }

        $privateUnits = [];
        foreach (['Sea View Room A', 'Sea View Room B', 'Garden Room C'] as $name) {
            $privateUnits[] = Unit::firstOrCreate(
                ['organizer_id' => $organizer->id, 'property_id' => $property->id, 'name' => $name],
                [
                    'room_type_id' => $privateRoomType->id,
                    'bed_category' => 'double',
                    'capacity' => 2,
                    'is_active' => true,
                ]
            );
        }

        // ── Programs ───────────────────────────────────────────────────────────
        $weekProgram = Program::firstOrCreate(
            ['organizer_id' => $organizer->id, 'name' => '7-Day Surf Week'],
            [
                'property_id' => $property->id,
                'type' => 'surf',
                'description' => '7 days of daily surf lessons (2h morning + 2h afternoon), video analysis, yoga, and accommodation.',
                'duration_days' => 7,
                'is_active' => true,
            ]
        );

        $intensiveProgram = Program::firstOrCreate(
            ['organizer_id' => $organizer->id, 'name' => '14-Day Surf Intensive'],
            [
                'property_id' => $property->id,
                'type' => 'surf',
                'description' => 'Two weeks of twice-daily surf coaching for intermediate to advanced surfers.',
                'duration_days' => 14,
                'is_active' => true,
            ]
        );

        // ── Add-Ons ────────────────────────────────────────────────────────────
        $boardRental = AddOn::firstOrCreate(
            ['organizer_id' => $organizer->id, 'name' => 'Board Rental'],
            [
                'property_id' => $property->id,
                'category' => 'equipment',
                'description' => 'Daily surfboard rental from our quiver.',
                'price' => 15.00,
                'currency' => 'EUR',
                'max_per_booking' => 14,
                'is_active' => true,
            ]
        );

        $wetsuitRental = AddOn::firstOrCreate(
            ['organizer_id' => $organizer->id, 'name' => 'Wetsuit Rental'],
            [
                'property_id' => $property->id,
                'category' => 'equipment',
                'description' => '3/2mm full wetsuit, daily rental.',
                'price' => 8.00,
                'currency' => 'EUR',
                'max_per_booking' => 14,
                'is_active' => true,
            ]
        );

        $airportTransfer = AddOn::firstOrCreate(
            ['organizer_id' => $organizer->id, 'name' => 'Airport Transfer (Lisbon)'],
            [
                'property_id' => $property->id,
                'category' => 'transfer',
                'description' => 'Return transfer between Lisbon Airport and the camp.',
                'price' => 45.00,
                'currency' => 'EUR',
                'max_per_booking' => 1,
                'is_active' => true,
            ]
        );

        $breakfastPlan = AddOn::firstOrCreate(
            ['organizer_id' => $organizer->id, 'name' => 'Breakfast Plan'],
            [
                'property_id' => $property->id,
                'category' => 'meal',
                'description' => 'Daily breakfast included (fruit, eggs, toast, coffee).',
                'price' => 9.00,
                'currency' => 'EUR',
                'max_per_booking' => 14,
                'is_active' => true,
            ]
        );

        // Attach add-ons to programs
        $weekProgram->addOns()->syncWithoutDetaching([
            $boardRental->id => ['is_default' => true],
            $wetsuitRental->id => ['is_default' => false],
            $airportTransfer->id => ['is_default' => false],
            $breakfastPlan->id => ['is_default' => true],
        ]);

        $intensiveProgram->addOns()->syncWithoutDetaching([
            $boardRental->id => ['is_default' => true],
            $wetsuitRental->id => ['is_default' => true],
            $airportTransfer->id => ['is_default' => false],
            $breakfastPlan->id => ['is_default' => true],
        ]);

        // ── Pricing Rules ──────────────────────────────────────────────────────
        // Dorm bed — standard rate
        PricingRule::firstOrCreate(
            ['organizer_id' => $organizer->id, 'priceable_id' => $dormRoomType->id, 'name' => 'Dorm Standard Rate'],
            [
                'priceable_type' => 'App\\Models\\RoomType',
                'model' => 'per_night',
                'amount' => 35.00,
                'currency' => 'EUR',
                'priority' => 1,
                'is_active' => true,
            ]
        );

        // Private room — standard rate
        PricingRule::firstOrCreate(
            ['organizer_id' => $organizer->id, 'priceable_id' => $privateRoomType->id, 'name' => 'Private Standard Rate'],
            [
                'priceable_type' => 'App\\Models\\RoomType',
                'model' => 'per_night',
                'amount' => 90.00,
                'currency' => 'EUR',
                'priority' => 1,
                'is_active' => true,
            ]
        );

        // 7-Day program — fixed package
        PricingRule::firstOrCreate(
            ['organizer_id' => $organizer->id, 'priceable_id' => $weekProgram->id, 'name' => '7-Day Package'],
            [
                'priceable_type' => 'App\\Models\\Program',
                'model' => 'fixed_package',
                'amount' => 450.00,
                'currency' => 'EUR',
                'priority' => 1,
                'is_active' => true,
            ]
        );

        // High season — July/August premium on private rooms
        PricingRule::firstOrCreate(
            ['organizer_id' => $organizer->id, 'priceable_id' => $privateRoomType->id, 'name' => 'High Season (Jul-Aug)'],
            [
                'priceable_type' => 'App\\Models\\RoomType',
                'model' => 'per_night',
                'amount' => 120.00,
                'currency' => 'EUR',
                'start_date' => '2026-07-01',
                'end_date' => '2026-08-31',
                'priority' => 10,
                'is_active' => true,
            ]
        );

        // ── Discounts ──────────────────────────────────────────────────────────
        Discount::firstOrCreate(['organizer_id' => $organizer->id, 'code' => 'SURF10'], [
            'type' => 'percentage',
            'value' => 10.00,
            'max_uses' => 100,
            'uses_count' => 0,
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
            'is_active' => true,
        ]);

        Discount::firstOrCreate(['organizer_id' => $organizer->id, 'code' => 'LONGSTAY'], [
            'type' => 'long_stay',
            'value' => 15.00,
            'min_nights' => 14,
            'max_uses' => null,
            'uses_count' => 0,
            'is_active' => true,
        ]);

        // ── Availability Rules ─────────────────────────────────────────────────
        // Open all year for the main house units
        foreach (array_merge($dormUnits, $privateUnits) as $unit) {
            AvailabilityRule::firstOrCreate(
                ['organizer_id' => $organizer->id, 'ruleable_id' => $unit->id, 'rule_type' => 'date_range'],
                [
                    'ruleable_type' => 'App\\Models\\Unit',
                    'start_date' => '2026-01-01',
                    'end_date' => '2026-12-31',
                    'weekday_mask' => '1111111',
                    'capacity' => 1,
                ]
            );
        }

        // ── Booking Rules ──────────────────────────────────────────────────────
        BookingRule::firstOrCreate(
            ['organizer_id' => $organizer->id, 'property_id' => $property->id, 'program_id' => null],
            [
                'min_nights' => 3,
                'max_nights' => 28,
                'check_in_days' => '1000001', // Mon + Sun
                'min_advance_days' => 1,
                'max_advance_days' => 365,
            ]
        );

        // ── Payment Rule ───────────────────────────────────────────────────────
        PaymentRule::firstOrCreate(
            ['organizer_id' => $organizer->id, 'property_id' => $property->id],
            [
                'type' => 'deposit_then_balance',
                'deposit_percentage' => 30.00,
                'balance_due_days_before' => 30,
            ]
        );

        // ── Sample Customers ───────────────────────────────────────────────────
        $customer1 = Customer::firstOrCreate(
            ['organizer_id' => $organizer->id, 'email' => 'alice@example.com'],
            [
                'first_name' => 'Alice',
                'last_name' => 'Martin',
                'phone' => '+33 6 12 34 56 78',
                'nationality' => 'FR',
                'date_of_birth' => '1994-03-15',
                'status' => 'active',
                'preferred_locale' => 'en',
                'preferred_currency' => 'EUR',
            ]
        );

        $customer2 = Customer::firstOrCreate(
            ['organizer_id' => $organizer->id, 'email' => 'ben@example.com'],
            [
                'first_name' => 'Ben',
                'last_name' => 'Costa',
                'phone' => '+44 7700 900123',
                'nationality' => 'GB',
                'date_of_birth' => '1989-08-22',
                'status' => 'vip',
                'preferred_locale' => 'en',
                'preferred_currency' => 'EUR',
            ]
        );

        $customer3 = Customer::firstOrCreate(
            ['organizer_id' => $organizer->id, 'email' => 'carla@example.com'],
            [
                'first_name' => 'Carla',
                'last_name' => 'Vega',
                'phone' => '+34 612 345 678',
                'nationality' => 'ES',
                'status' => 'active',
                'preferred_locale' => 'es',
                'preferred_currency' => 'EUR',
            ]
        );

        // ── Sample Bookings ────────────────────────────────────────────────────
        // Confirmed booking — Alice, next week, dorm bed
        $booking1 = Booking::firstOrCreate(
            ['organizer_id' => $organizer->id, 'customer_id' => $customer1->id, 'check_in_date' => '2026-06-02'],
            [
                'property_id' => $property->id,
                'program_id' => $weekProgram->id,
                'check_out_date' => '2026-06-09',
                'nights' => 7,
                'guests' => 1,
                'status' => 'confirmed',
                'payment_status' => 'partial',
                'total_price' => 450.00,
                'currency' => 'EUR',
                'source' => 'direct',
            ]
        );
        $booking1->units()->syncWithoutDetaching([
            $dormUnits[0]->id => ['guests' => 1, 'price_per_night' => 35.00],
        ]);

        // Deposit recorded for booking1
        Payment::firstOrCreate(
            ['organizer_id' => $organizer->id, 'booking_id' => $booking1->id, 'amount' => 135.00],
            [
                'currency' => 'EUR',
                'method' => 'bank_transfer',
                'status' => 'completed',
                'paid_at' => now()->subDays(10),
                'notes' => '30% deposit',
            ]
        );

        // Pending booking — Ben, next month, private room
        $booking2 = Booking::firstOrCreate(
            ['organizer_id' => $organizer->id, 'customer_id' => $customer2->id, 'check_in_date' => '2026-07-07'],
            [
                'property_id' => $property->id,
                'check_out_date' => '2026-07-14',
                'nights' => 7,
                'guests' => 2,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'total_price' => 630.00,
                'currency' => 'EUR',
                'source' => 'direct',
            ]
        );
        $booking2->units()->syncWithoutDetaching([
            $privateUnits[0]->id => ['guests' => 2, 'price_per_night' => 90.00],
        ]);

        // Checked-out booking — Carla, last month, dorm + add-ons, fully paid
        $booking3 = Booking::firstOrCreate(
            ['organizer_id' => $organizer->id, 'customer_id' => $customer3->id, 'check_in_date' => '2026-04-14'],
            [
                'property_id' => $property->id,
                'program_id' => $weekProgram->id,
                'check_out_date' => '2026-04-21',
                'nights' => 7,
                'guests' => 1,
                'status' => 'checked_out',
                'payment_status' => 'paid',
                'total_price' => 540.00,
                'currency' => 'EUR',
                'source' => 'marketplace',
            ]
        );
        $booking3->units()->syncWithoutDetaching([
            $dormUnits[1]->id => ['guests' => 1, 'price_per_night' => 35.00],
        ]);
        $booking3->addOns()->syncWithoutDetaching([
            $boardRental->id => ['quantity' => 7, 'unit_price' => 15.00, 'total_price' => 105.00],
        ]);

        Payment::firstOrCreate(
            ['organizer_id' => $organizer->id, 'booking_id' => $booking3->id, 'amount' => 540.00],
            [
                'currency' => 'EUR',
                'method' => 'stripe',
                'status' => 'completed',
                'paid_at' => now()->subDays(45),
            ]
        );
    }
}
