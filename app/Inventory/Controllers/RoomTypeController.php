<?php

namespace App\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Inventory\Repositories\RoomTypeRepositoryInterface;
use App\Inventory\Requests\StoreRoomTypeRequest;
use App\Inventory\Requests\UpdateRoomTypeRequest;
use App\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class RoomTypeController extends Controller
{
    public function __construct(
        private RoomTypeRepositoryInterface $roomTypes,
        private TenantContext $tenantContext,
    ) {}

    #[OA\Get(
        path: '/room-types',
        summary: 'List room types for a property',
        security: [['bearerAuth' => []]],
        tags: ['Inventory'],
        parameters: [
            new OA\Parameter(name: 'property_id', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Room types list')]
    )]
    public function index(Request $request): JsonResponse
    {
        $request->validate(['property_id' => 'required|uuid|exists:properties,id']);
        return response()->json($this->roomTypes->paginate($request->property_id, $request->integer('per_page', 15)));
    }

    #[OA\Post(
        path: '/room-types',
        summary: 'Create a room type',
        security: [['bearerAuth' => []]],
        tags: ['Inventory'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['property_id', 'name'],
                properties: [
                    new OA\Property(property: 'property_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'name', type: 'string', example: 'Shared Dorm 6-Bed'),
                    new OA\Property(property: 'category', type: 'string', enum: ['shared_dorm', 'private_room', 'bungalow', 'tent', 'van_cabin', 'boat_cabin', 'other']),
                    new OA\Property(property: 'max_capacity', type: 'integer', example: 6),
                    new OA\Property(property: 'description', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Room type created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreRoomTypeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['organizer_id'] = $this->tenantContext->getOrganizerId();

        return response()->json($this->roomTypes->create($validated), Response::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/room-types/{id}',
        summary: 'Get a room type',
        security: [['bearerAuth' => []]],
        tags: ['Inventory'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Room type details'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(string $id): JsonResponse
    {
        $roomType = $this->roomTypes->findById($id);
        if (!$roomType) {
            return response()->json(['message' => 'Room type not found.'], Response::HTTP_NOT_FOUND);
        }
        return response()->json($roomType->load('units'));
    }

    #[OA\Put(
        path: '/room-types/{id}',
        summary: 'Update a room type',
        security: [['bearerAuth' => []]],
        tags: ['Inventory'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Room type updated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function update(UpdateRoomTypeRequest $request, string $id): JsonResponse
    {
        $roomType = $this->roomTypes->findById($id);
        if (!$roomType) {
            return response()->json(['message' => 'Room type not found.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();

        return response()->json($this->roomTypes->update($roomType, $validated));
    }

    #[OA\Delete(
        path: '/room-types/{id}',
        summary: 'Delete a room type',
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
        $roomType = $this->roomTypes->findById($id);
        if (!$roomType) {
            return response()->json(['message' => 'Room type not found.'], Response::HTTP_NOT_FOUND);
        }
        $this->roomTypes->delete($roomType);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
