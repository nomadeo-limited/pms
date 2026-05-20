<?php

namespace App\Booking\Controllers;

use App\Booking\Enums\BookingStatus;
use App\Booking\Requests\StoreBookingRequest;
use App\Booking\Requests\UpdateBookingRequest;
use App\Booking\UseCases\CreateBookingUseCase;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class BookingController extends Controller
{
    public function __construct(private CreateBookingUseCase $createBooking) {}

    #[OA\Get(path: '/bookings', summary: 'List bookings', security: [['bearerAuth' => []]], tags: ['Bookings'],
        parameters: [
            new OA\Parameter(name: 'property_id', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'check_in_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'check_in_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OA\Response(response: 200, description: 'Bookings list')])]
    public function index(Request $request): JsonResponse
    {
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

        return response()->json($query->orderBy('check_in_date')->paginate($request->integer('per_page', 15)));
    }

    #[OA\Post(path: '/bookings', summary: 'Create a booking', security: [['bearerAuth' => []]], tags: ['Bookings'],
        requestBody: new OA\RequestBody(required: true,
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
        responses: [
            new OA\Response(response: 201, description: 'Booking created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ])]
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $booking = $this->createBooking->execute($validated, auth('api')->id());

        return response()->json($booking, Response::HTTP_CREATED);
    }

    #[OA\Get(path: '/bookings/{id}', summary: 'Get a booking', security: [['bearerAuth' => []]], tags: ['Bookings'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Booking'), new OA\Response(response: 404, description: 'Not found')])]
    public function show(string $id): JsonResponse
    {
        $booking = Booking::with(['customer', 'property', 'program', 'units', 'addOns', 'payments'])->find($id);
        if (!$booking) {
            return response()->json(['message' => 'Booking not found.'], Response::HTTP_NOT_FOUND);
        }
        return response()->json($booking);
    }

    #[OA\Put(path: '/bookings/{id}', summary: 'Update a booking status', security: [['bearerAuth' => []]], tags: ['Bookings'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        requestBody: new OA\RequestBody(required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'string', enum: ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'no_show']),
                new OA\Property(property: 'notes', type: 'string'),
            ])),
        responses: [new OA\Response(response: 200, description: 'Updated'), new OA\Response(response: 404, description: 'Not found')])]
    public function update(UpdateBookingRequest $request, string $id): JsonResponse
    {
        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json(['message' => 'Booking not found.'], Response::HTTP_NOT_FOUND);
        }

        $booking->update($request->validated());

        return response()->json($booking->fresh()->load(['customer', 'property', 'units', 'addOns']));
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
            ->with(['customer', 'units'])
            ->get();

        return response()->json(['month' => $request->month, 'bookings' => $bookings]);
    }
}
