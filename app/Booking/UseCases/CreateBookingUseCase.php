<?php

namespace App\Booking\UseCases;

use App\Models\Booking;
use App\Models\BookingRule;
use App\Models\Program;
use App\Models\Unit;
use App\Tenant\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBookingUseCase
{
    public function __construct(private TenantContext $tenantContext) {}

    public function execute(array $data, ?string $createdBy): Booking
    {
        $checkIn = Carbon::parse($data['check_in_date']);
        $checkOut = Carbon::parse($data['check_out_date']);
        $nights = $checkIn->diffInDays($checkOut);

        [$units, $program] = $this->resolveUnits($data);

        $this->validateBookingRules($data, $nights, $checkIn, $units);

        return DB::transaction(function () use ($data, $nights, $createdBy, $units, $program) {
            $totalPrice = $data['total_price']
                ?? ($program?->base_price !== null ? (float) $program->base_price : 0);

            $currency = $data['currency']
                ?? $program?->currency
                ?? 'USD';

            $booking = Booking::create([
                'organizer_id' => $this->tenantContext->getOrganizerId(),
                'property_id' => $data['property_id'],
                'program_id' => $data['program_id'] ?? null,
                'customer_id' => $data['customer_id'],
                'check_in_date' => $data['check_in_date'],
                'check_out_date' => $data['check_out_date'],
                'nights' => $nights,
                'guests' => $data['guests'] ?? 1,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'total_price' => $totalPrice,
                'currency' => $currency,
                'discount_id' => $data['discount_id'] ?? null,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'source' => $data['source'] ?? 'direct',
                'external_id' => $data['external_id'] ?? null,
                'created_by' => $createdBy,
            ]);

            foreach ($units as $unitData) {
                $booking->units()->attach($unitData['unit_id'], [
                    'guests' => $unitData['guests'] ?? 1,
                    'price_per_night' => $unitData['price_per_night'] ?? 0,
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

            return $booking->load(['customer', 'property', 'program', 'units', 'addOns']);
        });
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

        $unit = $unitQuery->first();

        if (!$unit) {
            throw ValidationException::withMessages([
                'program_id' => 'No available units for this program on the requested dates.',
            ]);
        }

        return [[['unit_id' => $unit->id, 'guests' => $data['guests'] ?? 1]], $program];
    }

    private function validateBookingRules(array $data, int $nights, Carbon $checkIn, array $units): void
    {
        $rules = BookingRule::where(function ($q) use ($data) {
            $q->where('property_id', $data['property_id'])
                ->orWhereNull('property_id');
        })->get();

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
