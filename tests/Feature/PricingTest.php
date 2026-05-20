<?php

namespace Tests\Feature;

use App\Models\Discount;
use App\Models\PricingRule;
use App\Models\Property;
use App\Models\Unit;
use App\Models\RoomType;
use Tests\TestCase;

class PricingTest extends TestCase
{
    private function scaffoldOrg(): array
    {
        $org = $this->createOrganizer('pricing-test');
        $admin = $this->createOrganizerAdmin($org);
        $property = Property::create([
            'organizer_id' => $org->id,
            'name' => 'Pricing Camp',
            'slug' => 'pricing-camp',
            'type' => 'surf_camp',
            'country' => 'CR',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ]);
        return compact('org', 'admin', 'property');
    }

    // --- Pricing rules ---

    public function test_create_pricing_rule(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldOrg();

        $this->postJson('/api/v1/pricing-rules', [
            'priceable_type' => 'unit',
            'priceable_id' => $property->id,
            'name' => 'High Season',
            'model' => 'per_night',
            'amount' => 120.00,
            'currency' => 'USD',
            'start_date' => '2027-06-01',
            'end_date' => '2027-08-31',
            'priority' => 10,
        ], $this->orgHeaders($admin, $org))
            ->assertCreated()
            ->assertJsonPath('name', 'High Season')
            ->assertJsonPath('model', 'per_night')
            ->assertJsonPath('amount', '120.00');
    }

    public function test_update_pricing_rule(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldOrg();

        $rule = PricingRule::create([
            'organizer_id' => $org->id,
            'priceable_type' => 'unit',
            'priceable_id' => $property->id,
            'name' => 'Low Season',
            'model' => 'per_night',
            'amount' => 60.00,
            'currency' => 'USD',
        ]);

        $this->putJson("/api/v1/pricing-rules/{$rule->id}", [
            'amount' => 55.00,
            'is_active' => false,
        ], $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('amount', '55.00')
            ->assertJsonPath('is_active', false);
    }

    public function test_delete_pricing_rule(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldOrg();

        $rule = PricingRule::create([
            'organizer_id' => $org->id,
            'priceable_type' => 'unit',
            'priceable_id' => $property->id,
            'name' => 'To Delete',
            'model' => 'fixed_package',
            'amount' => 500,
            'currency' => 'EUR',
        ]);

        $this->deleteJson("/api/v1/pricing-rules/{$rule->id}", [], $this->orgHeaders($admin, $org))
            ->assertNoContent();

        $this->assertDatabaseMissing('pricing_rules', ['id' => $rule->id]);
    }

    public function test_list_pricing_rules_returns_active_only(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldOrg();

        PricingRule::create(['organizer_id' => $org->id, 'priceable_type' => 'unit', 'priceable_id' => $property->id, 'name' => 'Active', 'model' => 'per_night', 'amount' => 80, 'currency' => 'USD', 'is_active' => true]);
        PricingRule::create(['organizer_id' => $org->id, 'priceable_type' => 'unit', 'priceable_id' => $property->id, 'name' => 'Inactive', 'model' => 'per_night', 'amount' => 80, 'currency' => 'USD', 'is_active' => false]);

        $this->getJson('/api/v1/pricing-rules', $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Active');
    }

    // --- Discounts ---

    public function test_create_percentage_discount(): void
    {
        ['org' => $org, 'admin' => $admin] = $this->scaffoldOrg();

        $this->postJson('/api/v1/discounts', [
            'code' => 'SURF20',
            'type' => 'percentage',
            'value' => 20.0,
            'max_uses' => 100,
            'valid_from' => '2027-01-01',
            'valid_until' => '2027-12-31',
        ], $this->orgHeaders($admin, $org))
            ->assertCreated()
            ->assertJsonPath('code', 'SURF20')
            ->assertJsonPath('type', 'percentage')
            ->assertJsonPath('value', '20.0000');
    }

    public function test_create_fixed_amount_discount(): void
    {
        ['org' => $org, 'admin' => $admin] = $this->scaffoldOrg();

        $this->postJson('/api/v1/discounts', [
            'type' => 'fixed_amount',
            'value' => 50.0,
            'currency' => 'EUR',
        ], $this->orgHeaders($admin, $org))
            ->assertCreated()
            ->assertJsonPath('type', 'fixed_amount')
            ->assertJsonPath('value', '50.0000');
    }

    public function test_delete_discount(): void
    {
        ['org' => $org, 'admin' => $admin] = $this->scaffoldOrg();

        $discount = Discount::create([
            'organizer_id' => $org->id,
            'code' => 'DEL10',
            'type' => 'percentage',
            'value' => 10,
        ]);

        $this->deleteJson("/api/v1/discounts/{$discount->id}", [], $this->orgHeaders($admin, $org))
            ->assertNoContent();

        $this->assertDatabaseMissing('discounts', ['id' => $discount->id]);
    }

    // --- Calculate endpoint ---

    private function scaffoldPriceableUnit(array $scaffold): Unit
    {
        $rt = RoomType::create([
            'organizer_id' => $scaffold['org']->id,
            'property_id' => $scaffold['property']->id,
            'name' => 'Standard',
            'category' => 'private_room',
            'max_capacity' => 4,
        ]);
        return Unit::create([
            'organizer_id' => $scaffold['org']->id,
            'property_id' => $scaffold['property']->id,
            'room_type_id' => $rt->id,
            'name' => 'Room 1',
            'bed_category' => 'double',
            'capacity' => 2,
        ]);
    }

    public function test_calculate_per_night_price(): void
    {
        $s = $this->scaffoldOrg();
        ['org' => $org, 'admin' => $admin] = $s;
        $unit = $this->scaffoldPriceableUnit($s);

        PricingRule::create([
            'organizer_id' => $org->id,
            'priceable_type' => 'unit',
            'priceable_id' => $unit->id,
            'name' => 'Standard Rate',
            'model' => 'per_night',
            'amount' => 100.00,
            'currency' => 'USD',
        ]);

        $this->getJson('/api/v1/pricing/calculate?' . http_build_query([
            'priceable_type' => 'unit',
            'priceable_id' => $unit->id,
            'check_in_date' => '2028-07-01',
            'check_out_date' => '2028-07-04', // 3 nights
            'guests' => 2,
        ]), $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('base_price', 300)
            ->assertJsonPath('total_price', 300)
            ->assertJsonPath('discount_amount', 0)
            ->assertJsonPath('nights', 3)
            ->assertJsonPath('currency', 'USD');
    }

    public function test_calculate_per_person_per_night_price(): void
    {
        $s = $this->scaffoldOrg();
        ['org' => $org, 'admin' => $admin] = $s;
        $unit = $this->scaffoldPriceableUnit($s);

        PricingRule::create([
            'organizer_id' => $org->id,
            'priceable_type' => 'unit',
            'priceable_id' => $unit->id,
            'name' => 'Group Rate',
            'model' => 'per_person_per_night',
            'amount' => 50.00,
            'currency' => 'USD',
        ]);

        $this->getJson('/api/v1/pricing/calculate?' . http_build_query([
            'priceable_type' => 'unit',
            'priceable_id' => $unit->id,
            'check_in_date' => '2028-08-01',
            'check_out_date' => '2028-08-04', // 3 nights × 2 guests = 6 × 50 = 300
            'guests' => 2,
        ]), $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('base_price', 300)
            ->assertJsonPath('nights', 3)
            ->assertJsonPath('guests', 2);
    }

    public function test_calculate_fixed_package_price(): void
    {
        $s = $this->scaffoldOrg();
        ['org' => $org, 'admin' => $admin] = $s;
        $unit = $this->scaffoldPriceableUnit($s);

        PricingRule::create([
            'organizer_id' => $org->id,
            'priceable_type' => 'unit',
            'priceable_id' => $unit->id,
            'name' => 'Weekly Package',
            'model' => 'fixed_package',
            'amount' => 700.00,
            'currency' => 'EUR',
        ]);

        $this->getJson('/api/v1/pricing/calculate?' . http_build_query([
            'priceable_type' => 'unit',
            'priceable_id' => $unit->id,
            'check_in_date' => '2028-09-01',
            'check_out_date' => '2028-09-08', // 7 nights, but fixed = 700 regardless
            'guests' => 3,
        ]), $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('base_price', 700)
            ->assertJsonPath('total_price', 700)
            ->assertJsonPath('currency', 'EUR');
    }

    public function test_calculate_applies_percentage_discount(): void
    {
        $s = $this->scaffoldOrg();
        ['org' => $org, 'admin' => $admin] = $s;
        $unit = $this->scaffoldPriceableUnit($s);

        PricingRule::create([
            'organizer_id' => $org->id,
            'priceable_type' => 'unit',
            'priceable_id' => $unit->id,
            'name' => 'Standard',
            'model' => 'per_night',
            'amount' => 100.00,
            'currency' => 'USD',
        ]);

        Discount::create([
            'organizer_id' => $org->id,
            'code' => 'SAVE20',
            'type' => 'percentage',
            'value' => 20.0,
            'valid_until' => '2099-12-31',
        ]);

        $this->getJson('/api/v1/pricing/calculate?' . http_build_query([
            'priceable_type' => 'unit',
            'priceable_id' => $unit->id,
            'check_in_date' => '2028-10-01',
            'check_out_date' => '2028-10-04', // 3 nights × 100 = 300 base; 20% = 60 off
            'guests' => 1,
            'discount_code' => 'SAVE20',
        ]), $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('base_price', 300)
            ->assertJsonPath('discount_amount', 60)
            ->assertJsonPath('total_price', 240);
    }

    public function test_calculate_applies_fixed_amount_discount(): void
    {
        $s = $this->scaffoldOrg();
        ['org' => $org, 'admin' => $admin] = $s;
        $unit = $this->scaffoldPriceableUnit($s);

        PricingRule::create([
            'organizer_id' => $org->id,
            'priceable_type' => 'unit',
            'priceable_id' => $unit->id,
            'name' => 'Standard',
            'model' => 'per_night',
            'amount' => 100.00,
            'currency' => 'USD',
        ]);

        Discount::create([
            'organizer_id' => $org->id,
            'code' => 'FLAT50',
            'type' => 'fixed_amount',
            'value' => 50.0,
            'valid_until' => '2099-12-31',
        ]);

        $this->getJson('/api/v1/pricing/calculate?' . http_build_query([
            'priceable_type' => 'unit',
            'priceable_id' => $unit->id,
            'check_in_date' => '2028-11-01',
            'check_out_date' => '2028-11-04',
            'guests' => 1,
            'discount_code' => 'FLAT50',
        ]), $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('base_price', 300)
            ->assertJsonPath('discount_amount', 50)
            ->assertJsonPath('total_price', 250);
    }

    public function test_calculate_ignores_expired_discount_code(): void
    {
        $s = $this->scaffoldOrg();
        ['org' => $org, 'admin' => $admin] = $s;
        $unit = $this->scaffoldPriceableUnit($s);

        PricingRule::create([
            'organizer_id' => $org->id,
            'priceable_type' => 'unit',
            'priceable_id' => $unit->id,
            'name' => 'Standard',
            'model' => 'per_night',
            'amount' => 100.00,
            'currency' => 'USD',
        ]);

        Discount::create([
            'organizer_id' => $org->id,
            'code' => 'EXPIRED',
            'type' => 'percentage',
            'value' => 20.0,
            'valid_until' => '2020-01-01', // expired
        ]);

        $this->getJson('/api/v1/pricing/calculate?' . http_build_query([
            'priceable_type' => 'unit',
            'priceable_id' => $unit->id,
            'check_in_date' => '2028-12-01',
            'check_out_date' => '2028-12-04',
            'guests' => 1,
            'discount_code' => 'EXPIRED',
        ]), $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('discount_amount', 0)
            ->assertJsonPath('discount_applied', null);
    }

    public function test_calculate_respects_discount_min_nights(): void
    {
        $s = $this->scaffoldOrg();
        ['org' => $org, 'admin' => $admin] = $s;
        $unit = $this->scaffoldPriceableUnit($s);

        PricingRule::create([
            'organizer_id' => $org->id,
            'priceable_type' => 'unit',
            'priceable_id' => $unit->id,
            'name' => 'Standard',
            'model' => 'per_night',
            'amount' => 100.00,
            'currency' => 'USD',
        ]);

        Discount::create([
            'organizer_id' => $org->id,
            'code' => 'WEEK10',
            'type' => 'percentage',
            'value' => 10.0,
            'min_nights' => 7,
        ]);

        // Only 3 nights — discount should not apply
        $this->getJson('/api/v1/pricing/calculate?' . http_build_query([
            'priceable_type' => 'unit',
            'priceable_id' => $unit->id,
            'check_in_date' => '2029-01-01',
            'check_out_date' => '2029-01-04',
            'guests' => 1,
            'discount_code' => 'WEEK10',
        ]), $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('discount_amount', 0)
            ->assertJsonPath('discount_applied', null);
    }

    public function test_calculate_returns_error_when_no_rule_found(): void
    {
        $s = $this->scaffoldOrg();
        ['org' => $org, 'admin' => $admin] = $s;
        $unit = $this->scaffoldPriceableUnit($s);

        // No pricing rule created

        $this->getJson('/api/v1/pricing/calculate?' . http_build_query([
            'priceable_type' => 'unit',
            'priceable_id' => $unit->id,
            'check_in_date' => '2029-02-01',
            'check_out_date' => '2029-02-04',
            'guests' => 1,
        ]), $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('total_price', null)
            ->assertJsonPath('error', 'No active pricing rule found for this period.');
    }

    public function test_calculate_uses_highest_priority_rule(): void
    {
        $s = $this->scaffoldOrg();
        ['org' => $org, 'admin' => $admin] = $s;
        $unit = $this->scaffoldPriceableUnit($s);

        // Lower priority: $80/night
        PricingRule::create([
            'organizer_id' => $org->id,
            'priceable_type' => 'unit',
            'priceable_id' => $unit->id,
            'name' => 'Base Rate',
            'model' => 'per_night',
            'amount' => 80.00,
            'currency' => 'USD',
            'priority' => 1,
        ]);

        // Higher priority: $150/night (high season)
        PricingRule::create([
            'organizer_id' => $org->id,
            'priceable_type' => 'unit',
            'priceable_id' => $unit->id,
            'name' => 'High Season',
            'model' => 'per_night',
            'amount' => 150.00,
            'currency' => 'USD',
            'priority' => 10,
        ]);

        $this->getJson('/api/v1/pricing/calculate?' . http_build_query([
            'priceable_type' => 'unit',
            'priceable_id' => $unit->id,
            'check_in_date' => '2029-03-01',
            'check_out_date' => '2029-03-04', // 3 nights × 150 = 450
            'guests' => 1,
        ]), $this->orgHeaders($admin, $org))
            ->assertOk()
            ->assertJsonPath('base_price', 450)
            ->assertJsonPath('rule_applied.name', 'High Season');
    }

    public function test_tenant_isolation_pricing_rules(): void
    {
        ['org' => $org, 'admin' => $admin, 'property' => $property] = $this->scaffoldOrg();
        $orgB = $this->createOrganizer('pricing-org-b');
        $adminB = $this->createOrganizerAdmin($orgB);

        PricingRule::create(['organizer_id' => $org->id, 'priceable_type' => 'unit', 'priceable_id' => $property->id, 'name' => 'Org A Rule', 'model' => 'per_night', 'amount' => 100, 'currency' => 'USD']);

        $this->getJson('/api/v1/pricing-rules', $this->orgHeaders($adminB, $orgB))
            ->assertOk()
            ->assertJsonPath('total', 0);
    }
}
