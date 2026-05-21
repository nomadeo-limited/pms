<?php

namespace App\Booking\UseCases;

use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\BookingRule;
use App\Models\Program;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Pricing\UseCases\CalculatePriceUseCase;
use App\Tenant\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBookingUseCase
{
    public function __construct(
        private TenantContext $tenantContext,
        private CalculatePriceUseCase $calculatePrice,
    ) {}

    public function execute(array $data, ?string $createdBy): Booking
    {
        $checkIn = Carbon::parse($data['check_in_date']);
        $checkOut = Carbon::parse($data['check_out_date']);
        $nights = $checkIn->diffInDays($checkOut);

        [$units, $program] = $this->resolveUnits($data);

        $this->validateAvailabilityRules($data, $units);
        $this->validateBookingRules($data, $nights, $checkIn, $units);

        $guests = $data['guests'] ?? 1;
        [$totalPrice, $currency, $discountAmount, $discountId, $pricePerNightMap] = $this->resolvePrice($data, $program, $guests, $units);
        $taxAmount = $this->calculateTax($data['property_id'], $totalPrice);

        return DB::transaction(function () use ($data, $nights, $createdBy, $units, $program, $totalPrice, $taxAmount, $currency, $discountAmount, $discountId, $guests, $pricePerNightMap) {

            $booking = Booking::create([
                'organizer_id' => $this->tenantContext->getOrganizerId(),
                'property_id' => $data['property_id'],
                'program_id' => $data['program_id'] ?? null,
                'customer_id' => $data['customer_id'],
                'check_in_date' => $data['check_in_date'],
                'check_out_date' => $data['check_out_date'],
                'nights' => $nights,
                'guests' => $guests,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'total_price' => $totalPrice,
                'tax_amount' => $taxAmount,
                'currency' => $currency,
                'discount_id' => $discountId,
                'discount_amount' => $discountAmount,
                'notes' => $data['notes'] ?? null,
                'source' => $data['source'] ?? 'direct',
                'external_id' => $data['external_id'] ?? null,
                'created_by' => $createdBy,
            ]);

            foreach ($units as $unitData) {
                $booking->units()->attach($unitData['unit_id'], [
                    'guests' => $unitData['guests'] ?? 1,
                    'price_per_night' => $unitData['price_per_night'] ?? $pricePerNightMap[$unitData['unit_id']] ?? 0,
                ]);
            }

            // Attach default add-ons from program (keyed by add_on_id so explicit ones can override)
            $addOnsToAttach = [];

            if ($program) {
                foreach ($program->addOns->where('pivot.is_default', true) as $addOn) {
                    $addOnsToAttach[$addOn->id] = [
                        'quantity' => $addOn->pivot->quantity,
                        'unit_price' => $addOn->price,
                        'total_price' => $addOn->price * $addOn->pivot->quantity,
                    ];
                }
            }

            // Explicit add-ons from request override program defaults
            foreach ($data['add_on_ids'] ?? [] as $addOnData) {
                $unitPrice = $addOnData['unit_price'] ?? 0;
                $quantity = $addOnData['quantity'] ?? 1;
                $addOnsToAttach[$addOnData['add_on_id']] = [
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $unitPrice * $quantity,
                ];
            }

            foreach ($addOnsToAttach as $addOnId => $pivot) {
                $booking->addOns()->attach($addOnId, $pivot);
            }

            foreach ($data['additional_guests'] ?? [] as $guest) {
                $booking->bookingGuests()->create($guest);
            }

            return $booking->load(['customer', 'property', 'program', 'units', 'addOns', 'bookingGuests']);
        });
    }

    private function calculateTax(string $propertyId, float $totalPrice): float
    {
        $taxes = TaxRate::where('property_id', $propertyId)
            ->where('is_active', true)
            ->where('is_inclusive', false)
            ->get();

        $taxAmount = 0.0;
        foreach ($taxes as $tax) {
            $taxAmount += ($tax->rate / 100) * $totalPrice;
        }

        return round($taxAmount, 2);
    }

    /**
     * Resolves total price, currency, discount amount, discount id, and per-unit price map.
     * Priority: explicit total_price > pricing rule (program or unit) > base_price × guests.
     *
     * @return array{0: float, 1: string, 2: float, 3: string|null, 4: array<string, float>}
     */
    private function resolvePrice(array $data, ?Program $program, int $guests, array $units): array
    {
        $noPrice = [0.0, $data['currency'] ?? 'USD', 0.0, null, []];

        // Caller supplied an explicit price — honour it as-is.
        if (isset($data['total_price'])) {
            return [
                (float) $data['total_price'],
                $data['currency'] ?? $program?->currency ?? 'USD',
                (float) ($data['discount_amount'] ?? 0),
                $data['discount_id'] ?? null,
                [],
            ];
        }

        if ($program) {
            $result = $this->calculatePrice->execute(
                'program', $program->id,
                $data['check_in_date'], $data['check_out_date'],
                $guests,
                null,
                $data['discount_id'] ?? null,
            );

            if ($result['total_price'] !== null) {
                return [
                    (float) $result['total_price'],
                    $result['currency'],
                    (float) ($result['discount_amount'] ?? 0),
                    $result['discount_applied']['id'] ?? ($data['discount_id'] ?? null),
                    [],
                ];
            }

            // Fallback: base_price is per person per stay.
            $total = $program->base_price !== null
                ? (float) $program->base_price * $guests
                : 0.0;

            return [
                $total,
                $data['currency'] ?? $program->currency ?? 'USD',
                (float) ($data['discount_amount'] ?? 0),
                $data['discount_id'] ?? null,
                [],
            ];
        }

        // Direct unit booking — sum pricing rules per unit.
        $totalPrice = 0.0;
        $currency = $data['currency'] ?? 'USD';
        $pricePerNightMap = [];
        $nights = Carbon::parse($data['check_in_date'])->diffInDays($data['check_out_date']);

        foreach ($units as $unitData) {
            $result = $this->calculatePrice->execute(
                'unit', $unitData['unit_id'],
                $data['check_in_date'], $data['check_out_date'],
                $unitData['guests'] ?? 1,
                null,
                $data['discount_id'] ?? null,
            );

            if ($result['total_price'] !== null) {
                $totalPrice += (float) $result['total_price'];
                $currency = $result['currency'];
                // Derive price_per_night from the rule for the invoice pivot
                $pricePerNightMap[$unitData['unit_id']] = $nights > 0
                    ? round((float) $result['total_price'] / $nights, 2)
                    : (float) $result['total_price'];
            }
        }

        return [
            $totalPrice,
            $currency,
            (float) ($data['discount_amount'] ?? 0),
            $data['discount_id'] ?? null,
            $pricePerNightMap,
        ];
    }

    /**
     * Resolves which units to book.
     * - If unit_ids provided: use them directly.
     * - If program_id provided without unit_ids: auto-assign from program's room type (or any active unit).
     *
     * @return array{0: array, 1: Program|null}
     */
    private function resolveUnits(array $data): array
    {
        if (!empty($data['unit_ids'])) {
            $this->validateDirectBookingCapacity($data);
            return [$data['unit_ids'], null];
        }

        if (empty($data['program_id'])) {
            throw ValidationException::withMessages([
                'unit_ids' => 'Unit IDs are required when no program is specified.',
            ]);
        }

        $program = Program::with([
            'addOns' => fn ($q) => $q->wherePivot('is_default', true),
        ])->find($data['program_id']);

        if (!$program) {
            throw ValidationException::withMessages([
                'program_id' => 'Program not found.',
            ]);
        }

        $bookedUnitIds = Booking::where('property_id', $data['property_id'])
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('check_in_date', '<', $data['check_out_date'])
            ->where('check_out_date', '>', $data['check_in_date'])
            ->with('units:id')
            ->get()
            ->pluck('units')
            ->flatten()
            ->pluck('id');

        $unitQuery = Unit::where('property_id', $data['property_id'])
            ->where('is_active', true)
            ->whereNotIn('id', $bookedUnitIds);

        if ($program->room_type_id) {
            $unitQuery->where('room_type_id', $program->room_type_id);
        }

        $guests = $data['guests'] ?? 1;
        $availableUnits = $unitQuery->orderBy('name')->get();

        $selectedUnits = [];
        $remaining = $guests;

        foreach ($availableUnits as $unit) {
            if ($remaining <= 0) {
                break;
            }
            $selectedUnits[] = ['unit_id' => $unit->id, 'guests' => min($unit->capacity, $remaining)];
            $remaining -= $unit->capacity;
        }

        if ($remaining > 0) {
            $canFit = $guests - max(0, $remaining);
            throw ValidationException::withMessages([
                'program_id' => "Not enough capacity available: can accommodate {$canFit} of {$guests} guests on the requested dates.",
            ]);
        }

        return [$selectedUnits, $program];
    }

    private function validateDirectBookingCapacity(array $data): void
    {
        $guests = $data['guests'] ?? 1;
        $unitIds = array_column($data['unit_ids'], 'unit_id');
        $totalCapacity = Unit::whereIn('id', $unitIds)->sum('capacity');

        if ($totalCapacity < $guests) {
            throw ValidationException::withMessages([
                'unit_ids' => "Selected unit(s) have a combined capacity of {$totalCapacity} but {$guests} guests were requested.",
            ]);
        }
    }

    private function validateAvailabilityRules(array $data, array $units): void
    {
        $unitIds = array_column($units, 'unit_id');
        $checkIn = Carbon::parse($data['check_in_date']);
        $checkOut = Carbon::parse($data['check_out_date'])->subDay();

        $roomTypeIds = Unit::whereIn('id', $unitIds)
            ->whereNotNull('room_type_id')
            ->pluck('room_type_id')
            ->unique()
            ->values();

        $blockingRules = AvailabilityRule::where('capacity', 0)
            ->where(function ($q) use ($unitIds, $roomTypeIds) {
                $q->where(fn($q2) => $q2->where('ruleable_type', 'unit')->whereIn('ruleable_id', $unitIds));
                if ($roomTypeIds->isNotEmpty()) {
                    $q->orWhere(fn($q2) => $q2->where('ruleable_type', 'room_type')->whereIn('ruleable_id', $roomTypeIds));
                }
            })
            ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $data['check_in_date']))
            ->where(fn($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $data['check_out_date']))
            ->get();

        if ($blockingRules->isEmpty()) {
            return;
        }

        $cursor = $checkIn->copy();
        while ($cursor->lte($checkOut)) {
            foreach ($blockingRules as $rule) {
                if ($rule->appliesOnDay($cursor)) {
                    throw ValidationException::withMessages([
                        'check_in_date' => 'One or more requested units are not available on the selected dates.',
                    ]);
                }
            }
            $cursor->addDay();
        }
    }

    private function validateBookingRules(array $data, int $nights, Carbon $checkIn, array $units): void
    {
        $rules = BookingRule::where(function ($q) use ($data) {
            $q->where('property_id', $data['property_id'])->orWhereNull('property_id');
        })
        ->where(function ($q) use ($data) {
            // Rule applies when it has no date range, or its range overlaps the booking dates
            $q->whereNull('start_date')
              ->orWhere(function ($q2) use ($data) {
                  $q2->where('start_date', '<=', $data['check_out_date'])
                     ->where(fn($q3) => $q3->whereNull('end_date')->orWhere('end_date', '>=', $data['check_in_date']));
              });
        })
        ->get();

        $today = Carbon::today();
        $advanceDays = $today->diffInDays($checkIn, false);

        foreach ($rules as $rule) {
            if ($rule->min_nights && $nights < $rule->min_nights) {
                throw ValidationException::withMessages([
                    'check_out_date' => "Minimum stay is {$rule->min_nights} nights.",
                ]);
            }
            if ($rule->max_nights && $nights > $rule->max_nights) {
                throw ValidationException::withMessages([
                    'check_out_date' => "Maximum stay is {$rule->max_nights} nights.",
                ]);
            }
            if ($rule->check_in_days && $rule->check_in_days !== '1111111') {
                $dayIndex = $checkIn->dayOfWeek;
                if ($rule->check_in_days[$dayIndex] !== '1') {
                    throw ValidationException::withMessages([
                        'check_in_date' => 'Check-in is not allowed on this day of the week.',
                    ]);
                }
            }
            if ($rule->min_advance_days !== null && $advanceDays < $rule->min_advance_days) {
                throw ValidationException::withMessages([
                    'check_in_date' => "Booking must be made at least {$rule->min_advance_days} day(s) in advance.",
                ]);
            }
            if ($rule->max_advance_days !== null && $advanceDays > $rule->max_advance_days) {
                throw ValidationException::withMessages([
                    'check_in_date' => "Booking cannot be made more than {$rule->max_advance_days} day(s) in advance.",
                ]);
            }
        }
    }
}
