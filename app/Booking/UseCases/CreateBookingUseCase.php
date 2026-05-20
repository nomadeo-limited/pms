<?php

namespace App\Booking\UseCases;

use App\Models\Booking;
use App\Models\BookingRule;
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
        $units = $data['unit_ids'];

        $this->validateBookingRules($data, $nights, $checkIn, $units);

        return DB::transaction(function () use ($data, $nights, $createdBy) {
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
                'total_price' => $data['total_price'] ?? 0,
                'currency' => $data['currency'] ?? 'USD',
                'discount_id' => $data['discount_id'] ?? null,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'source' => $data['source'] ?? 'direct',
                'external_id' => $data['external_id'] ?? null,
                'created_by' => $createdBy,
            ]);

            foreach ($data['unit_ids'] as $unitData) {
                $booking->units()->attach($unitData['unit_id'], [
                    'guests' => $unitData['guests'] ?? 1,
                    'price_per_night' => $unitData['price_per_night'] ?? 0,
                ]);
            }

            if (!empty($data['add_on_ids'])) {
                foreach ($data['add_on_ids'] as $addOnData) {
                    $unitPrice = $addOnData['unit_price'] ?? 0;
                    $quantity = $addOnData['quantity'] ?? 1;
                    $booking->addOns()->attach($addOnData['add_on_id'], [
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $unitPrice * $quantity,
                    ]);
                }
            }

            return $booking->load(['customer', 'property', 'units', 'addOns']);
        });
    }

    private function validateBookingRules(array $data, int $nights, Carbon $checkIn, array $units): void
    {
        $rules = BookingRule::where(function ($q) use ($data) {
            $q->where('property_id', $data['property_id'])
                ->orWhereNull('property_id');
        })->get();

        $today = Carbon::today();
        $advanceDays = $today->diffInDays($checkIn, false); // negative if check-in is in the past

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
