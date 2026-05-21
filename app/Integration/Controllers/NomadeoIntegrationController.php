<?php

namespace App\Integration\Controllers;

use App\Availability\UseCases\GetAvailabilityCalendarUseCase;
use App\Booking\UseCases\CreateBookingUseCase;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\IntegrationToken;
use App\Models\Program;
use App\Models\Property;
use App\Models\Unit;
use App\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class NomadeoIntegrationController extends Controller
{
    public function __construct(
        private TenantContext $tenantContext,
        private CreateBookingUseCase $createBooking,
        private GetAvailabilityCalendarUseCase $calendarUseCase,
    ) {}

    #[OA\Get(path: '/integration/{propertySlug}/availability', summary: 'Get per-day availability calendar for a property (marketplace)',
        tags: ['Integration'],
        parameters: [
            new OA\Parameter(name: 'propertySlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'start_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'program_id', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'Filter availability to units for this program\'s room type'),
        ],
        responses: [new OA\Response(response: 200, description: 'Per-day availability calendar'), new OA\Response(response: 404, description: 'Property or program not found')])]
    public function availability(Request $request, string $propertySlug): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'program_id' => 'nullable|uuid',
        ]);

        $organizerId = $this->tenantContext->getOrganizerId();
        $property = Property::withoutGlobalScopes()
            ->where('organizer_id', $organizerId)
            ->where('slug', $propertySlug)
            ->first();

        if (!$property) {
            return response()->json(['message' => 'Property not found.'], Response::HTTP_NOT_FOUND);
        }

        $roomTypeId = null;
        if ($request->program_id) {
            $program = Program::withoutGlobalScopes()
                ->where('organizer_id', $organizerId)
                ->where('id', $request->program_id)
                ->first();

            if (!$program) {
                return response()->json(['message' => 'Program not found.'], Response::HTTP_NOT_FOUND);
            }

            $roomTypeId = $program->room_type_id;
        }

        $calendar = $this->calendarUseCase->execute(
            $property->id,
            $request->start_date,
            $request->end_date,
            $roomTypeId,
        );

        return response()->json(array_merge(['property_slug' => $propertySlug], $calendar));
    }

    #[OA\Post(path: '/integration/{propertySlug}/bookings', summary: 'Create a booking from marketplace',
        requestBody: new OA\RequestBody(required: true,
            content: new OA\JsonContent(required: ['customer', 'check_in_date', 'check_out_date'],
                properties: [
                    new OA\Property(property: 'check_in_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'check_out_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'guests', type: 'integer'),
                    new OA\Property(property: 'external_id', type: 'string'),
                    new OA\Property(property: 'notes', type: 'string'),
                    new OA\Property(property: 'total_price', type: 'number'),
                    new OA\Property(property: 'currency', type: 'string'),
                    new OA\Property(property: 'program_id', type: 'string', format: 'uuid'),
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
                    new OA\Property(property: 'customer', properties: [
                        new OA\Property(property: 'first_name', type: 'string'),
                        new OA\Property(property: 'last_name', type: 'string'),
                        new OA\Property(property: 'email', type: 'string', format: 'email'),
                        new OA\Property(property: 'phone', type: 'string'),
                        new OA\Property(property: 'nationality', type: 'string'),
                        new OA\Property(property: 'document_type', type: 'string', enum: ['passport', 'national_id', 'other']),
                        new OA\Property(property: 'document_number', type: 'string'),
                        new OA\Property(property: 'document_country', type: 'string'),
                    ],
                        type: 'object'),
                    new OA\Property(property: 'additional_guests', type: 'array', items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'first_name', type: 'string'),
                            new OA\Property(property: 'last_name', type: 'string'),
                            new OA\Property(property: 'email', type: 'string', format: 'email'),
                            new OA\Property(property: 'nationality', type: 'string'),
                            new OA\Property(property: 'document_type', type: 'string', enum: ['passport', 'national_id', 'other']),
                            new OA\Property(property: 'document_number', type: 'string'),
                            new OA\Property(property: 'document_country', type: 'string'),
                        ]
                    )),
                ])),
        tags: ['Integration'],
        parameters: [new OA\Parameter(name: 'propertySlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 201, description: 'Booking created'), new OA\Response(response: 404, description: 'Not found')])]
    public function createBooking(Request $request, string $propertySlug): JsonResponse
    {
        $validated = $request->validate([
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after:check_in_date',
            'guests' => 'nullable|integer|min:1',
            'external_id' => 'nullable|string',
            'total_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'notes' => 'nullable|string',
            'program_id' => 'nullable|uuid|exists:programs,id',
            'unit_ids' => 'nullable|array|min:1',
            'unit_ids.*.unit_id' => 'required|uuid|exists:units,id',
            'unit_ids.*.guests' => 'nullable|integer|min:1',
            'unit_ids.*.price_per_night' => 'nullable|numeric|min:0',
            'add_on_ids' => 'nullable|array',
            'add_on_ids.*.add_on_id' => 'required|uuid|exists:add_ons,id',
            'add_on_ids.*.quantity' => 'nullable|integer|min:1',
            'add_on_ids.*.unit_price' => 'nullable|numeric|min:0',
            'customer.first_name' => 'required|string|max:100',
            'customer.last_name' => 'required|string|max:100',
            'customer.email' => 'required|email',
            'customer.phone' => 'nullable|string|max:50',
            'customer.nationality' => 'nullable|string|size:2',
            'customer.document_type' => 'nullable|in:passport,national_id,other',
            'customer.document_number' => 'nullable|string|max:100',
            'customer.document_country' => 'nullable|string|size:2',
            'additional_guests' => 'nullable|array',
            'additional_guests.*.first_name' => 'required|string|max:100',
            'additional_guests.*.last_name' => 'required|string|max:100',
            'additional_guests.*.email' => 'nullable|email',
            'additional_guests.*.phone' => 'nullable|string|max:50',
            'additional_guests.*.nationality' => 'nullable|string|size:2',
            'additional_guests.*.document_type' => 'nullable|in:passport,national_id,other',
            'additional_guests.*.document_number' => 'nullable|string|max:100',
            'additional_guests.*.document_country' => 'nullable|string|size:2',
        ]);

        $organizerId = $this->tenantContext->getOrganizerId();
        $property = Property::withoutGlobalScopes()
            ->where('organizer_id', $organizerId)
            ->where('slug', $propertySlug)
            ->first();

        if (!$property) {
            return response()->json(['message' => 'Property not found.'], Response::HTTP_NOT_FOUND);
        }

        $customerData = $validated['customer'];
        $customer = Customer::withoutGlobalScopes()
            ->where('organizer_id', $organizerId)
            ->where('email', $customerData['email'])
            ->first();

        if ($customer) {
            $customer->update(array_filter($customerData, fn($v) => $v !== null));
        } else {
            $customer = Customer::create(array_merge($customerData, ['organizer_id' => $organizerId]));
        }

        $unitIds = $validated['unit_ids'] ?? null;
        $programId = $validated['program_id'] ?? null;
        $guests = $validated['guests'] ?? 1;

        // When neither program_id nor unit_ids are given, greedily assign units across the property.
        if (!$unitIds && !$programId) {
            $bookedUnitIds = Booking::where('property_id', $property->id)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->where('check_in_date', '<', $validated['check_out_date'])
                ->where('check_out_date', '>', $validated['check_in_date'])
                ->with('units:id')
                ->get()
                ->pluck('units')->flatten()->pluck('id');

            $availableUnits = Unit::withoutGlobalScopes()
                ->where('property_id', $property->id)
                ->where('is_active', true)
                ->whereNotIn('id', $bookedUnitIds)
                ->orderBy('name')
                ->get(['id', 'capacity']);

            $unitIds = [];
            $remaining = $guests;
            foreach ($availableUnits as $unit) {
                if ($remaining <= 0) break;
                $unitIds[] = ['unit_id' => $unit->id, 'guests' => min($unit->capacity, $remaining)];
                $remaining -= $unit->capacity;
            }

            if ($remaining > 0) {
                $canFit = $guests - max(0, $remaining);
                return response()->json([
                    'message' => "Not enough capacity available: can accommodate {$canFit} of {$guests} guests on the requested dates.",
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $booking = $this->createBooking->execute([
            'property_id' => $property->id,
            'customer_id' => $customer->id,
            'program_id' => $programId,
            'check_in_date' => $validated['check_in_date'],
            'check_out_date' => $validated['check_out_date'],
            'guests' => $guests,
            'total_price' => $validated['total_price'] ?? null,
            'currency' => $validated['currency'] ?? $property->currency,
            'notes' => $validated['notes'] ?? null,
            'source' => 'marketplace',
            'external_id' => $validated['external_id'] ?? null,
            'unit_ids' => $unitIds,
            'add_on_ids' => $validated['add_on_ids'] ?? [],
            'additional_guests' => $validated['additional_guests'] ?? [],
        ], null);

        return response()->json($booking, Response::HTTP_CREATED);
    }

    #[OA\Post(path: '/integration/{propertySlug}/customers', summary: 'Upsert a customer from marketplace',
        requestBody: new OA\RequestBody(required: true,
            content: new OA\JsonContent(required: ['first_name', 'last_name', 'email'],
                properties: [
                    new OA\Property(property: 'first_name', type: 'string'),
                    new OA\Property(property: 'last_name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'external_id', type: 'string'),
                ])),
        tags: ['Integration'],
        parameters: [new OA\Parameter(name: 'propertySlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'Customer upserted')])]
    public function upsertCustomer(Request $request, string $propertySlug): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:50',
            'nationality' => 'nullable|string|size:2',
            'external_id' => 'nullable|string',
        ]);

        $organizerId = $this->tenantContext->getOrganizerId();

        $customer = Customer::withoutGlobalScopes()
            ->where('organizer_id', $organizerId)
            ->where('email', $validated['email'])
            ->first();

        if ($customer) {
            $customer->update($validated);
        } else {
            $customer = Customer::create(array_merge($validated, ['organizer_id' => $organizerId]));
        }

        return response()->json($customer);
    }

    #[OA\Post(path: '/integration/tokens', summary: 'Create an integration token (organizer admin only)', security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true,
            content: new OA\JsonContent(required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Nomadeo Marketplace'),
                    new OA\Property(property: 'property_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
                ])),
        tags: ['Integration'],
        responses: [new OA\Response(response: 201, description: 'Token created — store plaintext token, it is shown once')])]
    public function createToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'property_id' => 'nullable|uuid|exists:properties,id',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $plaintext = Str::random(64);

        $token = IntegrationToken::create([
            'organizer_id' => $this->tenantContext->getOrganizerId(),
            'property_id' => $validated['property_id'] ?? null,
            'name' => $validated['name'],
            'token_hash' => Hash::make($plaintext),
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'token' => $plaintext,
            'id' => $token->id,
            'name' => $token->name,
            'note' => 'Store this token securely — it will not be shown again.',
        ], Response::HTTP_CREATED);
    }
}
