<?php

namespace App\Integration\Controllers;

use App\Availability\UseCases\GetAvailabilityCalendarUseCase;
use App\Booking\UseCases\CreateBookingUseCase;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\IntegrationToken;
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
        ],
        responses: [new OA\Response(response: 200, description: 'Per-day availability calendar'), new OA\Response(response: 404, description: 'Property not found')])]
    public function availability(Request $request, string $propertySlug): JsonResponse
    {
        $request->validate(['start_date' => 'required|date', 'end_date' => 'required|date|after:start_date']);

        $organizerId = $this->tenantContext->getOrganizerId();
        $property = Property::withoutGlobalScopes()
            ->where('organizer_id', $organizerId)
            ->where('slug', $propertySlug)
            ->first();

        if (!$property) {
            return response()->json(['message' => 'Property not found.'], Response::HTTP_NOT_FOUND);
        }

        $calendar = $this->calendarUseCase->execute(
            $property->id,
            $request->start_date,
            $request->end_date,
        );

        return response()->json(array_merge(['property_slug' => $propertySlug], $calendar));
    }

    #[OA\Post(path: '/integration/{propertySlug}/bookings', summary: 'Create a booking from marketplace',
        tags: ['Integration'],
        parameters: [new OA\Parameter(name: 'propertySlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(required: true,
            content: new OA\JsonContent(required: ['customer', 'check_in_date', 'check_out_date'],
                properties: [
                    new OA\Property(property: 'check_in_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'check_out_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'guests', type: 'integer'),
                    new OA\Property(property: 'external_id', type: 'string'),
                    new OA\Property(property: 'customer', type: 'object',
                        properties: [
                            new OA\Property(property: 'first_name', type: 'string'),
                            new OA\Property(property: 'last_name', type: 'string'),
                            new OA\Property(property: 'email', type: 'string', format: 'email'),
                        ]),
                ])),
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
            'unit_ids' => 'nullable|array|min:1',
            'unit_ids.*.unit_id' => 'required|uuid|exists:units,id',
            'unit_ids.*.guests' => 'nullable|integer|min:1',
            'unit_ids.*.price_per_night' => 'nullable|numeric|min:0',
            'customer.first_name' => 'required|string|max:100',
            'customer.last_name' => 'required|string|max:100',
            'customer.email' => 'required|email',
        ]);

        $organizerId = $this->tenantContext->getOrganizerId();
        $property = Property::withoutGlobalScopes()
            ->where('organizer_id', $organizerId)
            ->where('slug', $propertySlug)
            ->first();

        if (!$property) {
            return response()->json(['message' => 'Property not found.'], Response::HTTP_NOT_FOUND);
        }

        $customer = Customer::withoutGlobalScopes()
            ->where('organizer_id', $organizerId)
            ->where('email', $validated['customer']['email'])
            ->first();

        if (!$customer) {
            $customer = Customer::create([
                'organizer_id' => $organizerId,
                'first_name' => $validated['customer']['first_name'],
                'last_name' => $validated['customer']['last_name'],
                'email' => $validated['customer']['email'],
            ]);
        }

        // Use provided units, or auto-assign the first available unit for the property
        $unitIds = $validated['unit_ids'] ?? null;

        if (!$unitIds) {
            $bookedUnitIds = Booking::where('property_id', $property->id)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->where('check_in_date', '<', $validated['check_out_date'])
                ->where('check_out_date', '>', $validated['check_in_date'])
                ->with('units:id')
                ->get()
                ->pluck('units')
                ->flatten()
                ->pluck('id');

            $availableUnit = Unit::withoutGlobalScopes()
                ->where('property_id', $property->id)
                ->where('is_active', true)
                ->whereNotIn('id', $bookedUnitIds)
                ->first();

            if (!$availableUnit) {
                return response()->json(['message' => 'No available units for the requested dates.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $unitIds = [['unit_id' => $availableUnit->id, 'guests' => $validated['guests'] ?? 1]];
        }

        $booking = $this->createBooking->execute([
            'property_id' => $property->id,
            'customer_id' => $customer->id,
            'check_in_date' => $validated['check_in_date'],
            'check_out_date' => $validated['check_out_date'],
            'guests' => $validated['guests'] ?? 1,
            'total_price' => $validated['total_price'] ?? 0,
            'currency' => $validated['currency'] ?? $property->currency,
            'source' => 'marketplace',
            'external_id' => $validated['external_id'] ?? null,
            'unit_ids' => $unitIds,
        ], null);

        return response()->json($booking, Response::HTTP_CREATED);
    }

    #[OA\Post(path: '/integration/{propertySlug}/customers', summary: 'Upsert a customer from marketplace',
        tags: ['Integration'],
        parameters: [new OA\Parameter(name: 'propertySlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(required: true,
            content: new OA\JsonContent(required: ['first_name', 'last_name', 'email'],
                properties: [
                    new OA\Property(property: 'first_name', type: 'string'),
                    new OA\Property(property: 'last_name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'external_id', type: 'string'),
                ])),
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
        tags: ['Integration'],
        requestBody: new OA\RequestBody(required: true,
            content: new OA\JsonContent(required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Nomadeo Marketplace'),
                    new OA\Property(property: 'property_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
                ])),
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
