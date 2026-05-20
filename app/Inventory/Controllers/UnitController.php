<?php

namespace App\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Inventory\Repositories\UnitRepositoryInterface;
use App\Inventory\Requests\StoreUnitRequest;
use App\Inventory\Requests\UpdateUnitRequest;
use App\Inventory\UseCases\GetInventoryAvailabilityUseCase;
use App\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class UnitController extends Controller
{
    public function __construct(
        private UnitRepositoryInterface $units,
        private GetInventoryAvailabilityUseCase $availabilityUseCase,
        private TenantContext $tenantContext,
    ) {}

    #[OA\Get(
        path: '/units',
        summary: 'List bookable units for a property',
        security: [['bearerAuth' => []]],
        tags: ['Inventory'],
        parameters: [
            new OA\Parameter(name: 'property_id', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Units list')]
    )]
    public function index(Request $request): JsonResponse
    {
        $request->validate(['property_id' => 'required|uuid|exists:properties,id']);
        return response()->json($this->units->paginate($request->property_id, $request->integer('per_page', 15)));
    }

    #[OA\Post(
        path: '/units',
        summary: 'Create a bookable unit',
        security: [['bearerAuth' => []]],
        tags: ['Inventory'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['property_id', 'room_type_id', 'name'],
                properties: [
                    new OA\Property(property: 'property_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'room_type_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'name', type: 'string', example: 'Bunk 3A'),
                    new OA\Property(property: 'bed_category', type: 'string', enum: ['bunk_bed', 'single', 'double', 'queen', 'king', 'futon', 'hammock']),
                    new OA\Property(property: 'capacity', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Unit created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreUnitRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['organizer_id'] = $this->tenantContext->getOrganizerId();

        return response()->json($this->units->create($validated), Response::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/units/{id}',
        summary: 'Get a unit',
        security: [['bearerAuth' => []]],
        tags: ['Inventory'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Unit details'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(string $id): JsonResponse
    {
        $unit = $this->units->findById($id);
        if (!$unit) {
            return response()->json(['message' => 'Unit not found.'], Response::HTTP_NOT_FOUND);
        }
        return response()->json($unit);
    }

    #[OA\Put(
        path: '/units/{id}',
        summary: 'Update a unit',
        security: [['bearerAuth' => []]],
        tags: ['Inventory'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Unit updated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function update(UpdateUnitRequest $request, string $id): JsonResponse
    {
        $unit = $this->units->findById($id);
        if (!$unit) {
            return response()->json(['message' => 'Unit not found.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();

        return response()->json($this->units->update($unit, $validated));
    }

    #[OA\Get(
        path: '/units/availability',
        summary: 'Get available units for a property and date range',
        security: [['bearerAuth' => []]],
        tags: ['Inventory'],
        parameters: [
            new OA\Parameter(name: 'property_id', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'check_in_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'check_out_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Available units grouped by room type'),
            new OA\Response(response: 404, description: 'Property not found'),
        ]
    )]
    public function availability(Request $request): JsonResponse
    {
        $request->validate([
            'property_id' => 'required|uuid|exists:properties,id',
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after:check_in_date',
        ]);

        $result = $this->availabilityUseCase->execute(
            $request->property_id,
            $request->check_in_date,
            $request->check_out_date,
        );

        return response()->json($result);
    }

    #[OA\Delete(
        path: '/units/{id}',
        summary: 'Delete a unit',
        security: [['bearerAuth' => []]],
        tags: ['Inventory'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(string $id): JsonResponse
    {
        $unit = $this->units->findById($id);
        if (!$unit) {
            return response()->json(['message' => 'Unit not found.'], Response::HTTP_NOT_FOUND);
        }
        $this->units->delete($unit);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
