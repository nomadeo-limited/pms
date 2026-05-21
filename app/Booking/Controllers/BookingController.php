<?php

namespace App\Booking\Controllers;

use App\Booking\Enums\BookingStatus;
use App\Booking\Requests\StoreBookingRequest;
use App\Booking\Requests\UpdateBookingRequest;
use App\Booking\UseCases\CreateBookingUseCase;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\TaxRate;
use App\Models\UnitHousekeeping;
use App\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class BookingController extends Controller
{
    public function __construct(private readonly CreateBookingUseCase $createBooking) {}

    #[OA\Get(path: '/bookings', summary: 'List bookings', security: [['bearerAuth' => []]], tags: ['Bookings'],
        parameters: [
            new OA\Parameter(name: 'property_id', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'check_in_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'check_in_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'sort_by', in: 'query', schema: new OA\Schema(type: 'string', default: 'check_in_date', enum: ['check_in_date', 'check_out_date', 'created_at'])),
            new OA\Parameter(name: 'sort_dir', in: 'query', schema: new OA\Schema(type: 'string', default: 'asc', enum: ['asc', 'desc'])),
        ],
        responses: [new OA\Response(response: 200, description: 'Bookings list')])]
    public function index(Request $request): JsonResponse
    {
        $allowedSorts = ['check_in_date', 'check_out_date', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSorts) ? $request->sort_by : 'check_in_date';
        $sortDir = $request->sort_dir === 'desc' ? 'desc' : 'asc';

        $query = Booking::with(['customer', 'property', 'program']);

        if ($request->property_id) {
            $query->where('property_id', $request->property_id);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->check_in_from) {
            $query->where('check_in_date', '>=', $request->check_in_from);
        }
        if ($request->check_in_to) {
            $query->where('check_in_date', '<=', $request->check_in_to);
        }

        return response()->json($query->orderBy($sortBy, $sortDir)->paginate($request->integer('per_page', 15)));
    }

    #[OA\Post(path: '/bookings', summary: 'Create a booking', security: [['bearerAuth' => []]], requestBody: new OA\RequestBody(required: true,
        content: new OA\JsonContent(required: ['property_id', 'customer_id', 'check_in_date', 'check_out_date'],
            properties: [
                new OA\Property(property: 'property_id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'customer_id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'program_id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'check_in_date', type: 'string', format: 'date'),
                new OA\Property(property: 'check_out_date', type: 'string', format: 'date'),
                new OA\Property(property: 'guests', type: 'integer', example: 1),
                new OA\Property(property: 'total_price', type: 'number', format: 'float'),
                new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
                new OA\Property(property: 'notes', type: 'string'),
                new OA\Property(property: 'unit_ids', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'unit_id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'guests', type: 'integer'),
                        new OA\Property(property: 'price_per_night', type: 'number'),
                    ]
                )),
                new OA\Property(property: 'add_on_ids', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'add_on_id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'quantity', type: 'integer'),
                        new OA\Property(property: 'unit_price', type: 'number'),
                    ]
                )),
            ])),
        tags: ['Bookings'],
        responses: [
            new OA\Response(response: 201, description: 'Booking created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ])]
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $booking = $this->createBooking->execute($validated, auth('api')->id());

        if ($booking->customer?->email) {
            \Illuminate\Support\Facades\Mail::to($booking->customer->email)
                ->queue(new \App\Mail\BookingConfirmationMail($booking));
        }

        return response()->json($booking, Response::HTTP_CREATED);
    }

    #[OA\Get(path: '/bookings/{id}', summary: 'Get a booking', security: [['bearerAuth' => []]], tags: ['Bookings'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Booking'), new OA\Response(response: 404, description: 'Not found')])]
    public function show(string $id): JsonResponse
    {
        $booking = Booking::with(['customer', 'property', 'program', 'units', 'addOns', 'payments', 'bookingGuests'])->find($id);
        if (!$booking) {
            return response()->json(['message' => 'Booking not found.'], Response::HTTP_NOT_FOUND);
        }
        return response()->json($booking);
    }

    #[OA\Put(path: '/bookings/{id}', summary: 'Update a booking status', security: [['bearerAuth' => []]], requestBody: new OA\RequestBody(required: true,
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'status', type: 'string', enum: ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'no_show']),
            new OA\Property(property: 'notes', type: 'string'),
        ])),
        tags: ['Bookings'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Updated'), new OA\Response(response: 404, description: 'Not found')])]
    public function update(UpdateBookingRequest $request, string $id): JsonResponse
    {
        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json(['message' => 'Booking not found.'], Response::HTTP_NOT_FOUND);
        }

        $previousStatus = $booking->status;
        $booking->update($request->validated());
        $newStatus = $booking->fresh()->status;

        if ($previousStatus !== $newStatus && in_array($newStatus, ['checked_in', 'checked_out'])) {
            $this->syncHousekeeping($booking->fresh(), $newStatus);
        }

        return response()->json($booking->fresh()->load(['customer', 'property', 'units', 'addOns']));
    }

    private function syncHousekeeping(Booking $booking, string $newStatus): void
    {
        $booking->loadMissing('units');
        $organizerId = app(TenantContext::class)->getOrganizerId();
        $today = Carbon::today()->toDateString();
        $housekeepingStatus = $newStatus === 'checked_in' ? 'occupied' : 'dirty';

        foreach ($booking->units as $unit) {
            UnitHousekeeping::updateOrCreate(
                ['unit_id' => $unit->id, 'date' => $today],
                [
                    'status'       => $housekeepingStatus,
                    'property_id'  => $booking->property_id,
                    'organizer_id' => $organizerId,
                ]
            );
        }
    }

    public function invoice(string $id): JsonResponse
    {
        $booking = Booking::with(['customer', 'property', 'program', 'units', 'addOns'])->find($id);
        if (!$booking) {
            return response()->json(['message' => 'Booking not found.'], Response::HTTP_NOT_FOUND);
        }

        $accommodationSubtotal = $booking->units->sum(fn($u) => $u->pivot->price_per_night * $booking->nights);
        $addOnsSubtotal = $booking->addOns->sum(fn($a) => $a->pivot->total_price);
        $discountAmount = (float) ($booking->discount_amount ?? 0);
        $totalBeforeTax = $accommodationSubtotal + $addOnsSubtotal - $discountAmount;
        $taxAmount = (float) ($booking->tax_amount ?? 0);

        $taxLines = TaxRate::where('property_id', $booking->property_id)
            ->where('is_active', true)
            ->get()
            ->map(fn($t) => [
                'name' => $t->name,
                'rate' => $t->rate,
                'amount' => round((float) $t->rate / 100 * $totalBeforeTax, 2),
            ]);

        return response()->json([
            'booking_id' => $booking->id,
            'line_items' => [
                ['description' => 'Accommodation', 'amount' => $accommodationSubtotal],
                ['description' => 'Add-ons', 'amount' => $addOnsSubtotal],
                ['description' => 'Discount', 'amount' => -$discountAmount],
            ],
            'tax_lines' => $taxLines,
            'total_before_tax' => $totalBeforeTax,
            'tax_amount' => $taxAmount,
            'total_price' => (float) $booking->total_price,
            'currency' => $booking->currency,
        ]);
    }

    #[OA\Get(path: '/bookings/calendar', summary: 'Get booking calendar view', security: [['bearerAuth' => []]], tags: ['Bookings'],
        parameters: [
            new OA\Parameter(name: 'property_id', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'month', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: '2026-06')),
        ],
        responses: [new OA\Response(response: 200, description: 'Calendar data')])]
    public function calendar(Request $request): JsonResponse
    {
        $request->validate([
            'property_id' => 'required|uuid|exists:properties,id',
            'month' => 'required|date_format:Y-m',
        ]);

        $start = \Carbon\Carbon::parse($request->month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $bookings = Booking::where('property_id', $request->property_id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('check_in_date', '<=', $end)
            ->where('check_out_date', '>=', $start)
            ->with(['customer', 'units', 'program', 'bookingGuests'])
            ->get();

        return response()->json(['month' => $request->month, 'bookings' => $bookings]);
    }
}
