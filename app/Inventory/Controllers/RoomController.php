<?php

namespace App\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Inventory\Repositories\RoomRepositoryInterface;
use App\Inventory\Requests\StoreRoomRequest;
use App\Inventory\Requests\UpdateRoomRequest;
use App\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class RoomController extends Controller
{
    public function __construct(
        private RoomRepositoryInterface $rooms,
        private TenantContext $tenantContext,
    ) {}

    #[OA\Get(
        path: '/rooms',
        summary: 'List rooms for a property',
        security: [['bearerAuth' => []]],
        tags: ['Inventory'],
        parameters: [
            new OA\Parameter(name: 'property_id', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'room_type_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Rooms list')]
    )]
    public function index(Request $request): JsonResponse
    {
        $request->validate(['property_id' => 'required|uuid|exists:properties,id']);
        return response()->json($this->rooms->paginate($request->property_id, $request->integer('per_page', 15)));
    }

    #[OA\Post(
        path: '/rooms',
        summary: 'Create a room',
        security: [['bearerAuth' => []]],
        tags: ['Inventory'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['property_id', 'room_type_id', 'name'],
                properties: [
                    new OA\Property(property: 'property_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'room_type_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'name', type: 'string', example: 'Dorm A'),
                    new OA\Property(property: 'floor', type: 'string', example: '1'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Room created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreRoomRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['organizer_id'] = $this->tenantContext->getOrganizerId();

        return response()->json($this->rooms->create($validated), Response::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/rooms/{id}',
        summary: 'Get a room',
        security: [['bearerAuth' => []]],
        tags: ['Inventory'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Room details'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(string $id): JsonResponse
    {
        $room = $this->rooms->findById($id);
        if (!$room) {
            return response()->json(['message' => 'Room not found.'], Response::HTTP_NOT_FOUND);
        }
        return response()->json($room->load('units'));
    }

    #[OA\Put(
        path: '/rooms/{id}',
        summary: 'Update a room',
        security: [['bearerAuth' => []]],
        tags: ['Inventory'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Room updated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function update(UpdateRoomRequest $request, string $id): JsonResponse
    {
        $room = $this->rooms->findById($id);
        if (!$room) {
            return response()->json(['message' => 'Room not found.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();

        return response()->json($this->rooms->update($room, $validated));
    }

    #[OA\Delete(
        path: '/rooms/{id}',
        summary: 'Delete a room',
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
        $room = $this->rooms->findById($id);
        if (!$room) {
            return response()->json(['message' => 'Room not found.'], Response::HTTP_NOT_FOUND);
        }
        $this->rooms->delete($room);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
