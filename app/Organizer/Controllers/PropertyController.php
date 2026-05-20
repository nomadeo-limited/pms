<?php

namespace App\Organizer\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Organizer\Repositories\OrganizerRepositoryInterface;
use App\Organizer\Repositories\PropertyRepositoryInterface;
use App\Organizer\Requests\StorePropertyRequest;
use App\Organizer\Requests\UpdatePropertyRequest;
use App\Organizer\UseCases\CreatePropertyUseCase;
use App\Organizer\UseCases\UpdatePropertyUseCase;
use App\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class PropertyController extends Controller
{
    public function __construct(
        private PropertyRepositoryInterface $properties,
        private OrganizerRepositoryInterface $organizers,
        private CreatePropertyUseCase $createProperty,
        private UpdatePropertyUseCase $updateProperty,
        private TenantContext $tenantContext,
    ) {}

    #[OA\Get(
        path: '/properties',
        summary: 'List properties for the current organizer',
        security: [['bearerAuth' => []]],
        tags: ['Properties'],
        parameters: [new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15))],
        responses: [new OA\Response(response: 200, description: 'Paginated properties list')]
    )]
    public function index(Request $request): JsonResponse
    {
        $organizerId = $this->tenantContext->getOrganizerId()
            ?? $request->query('organizer_id');

        if (!$organizerId) {
            return response()->json(['message' => 'Organizer context required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(
            $this->properties->paginate($organizerId, $request->integer('per_page', 15))
        );
    }

    #[OA\Post(
        path: '/properties',
        summary: 'Create a new property',
        security: [['bearerAuth' => []]],
        tags: ['Properties'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Main Surf House'),
                    new OA\Property(property: 'type', type: 'string', enum: ['surf_camp', 'yoga_retreat', 'hostel', 'guesthouse', 'retreat_center', 'other']),
                    new OA\Property(property: 'address', type: 'string'),
                    new OA\Property(property: 'city', type: 'string'),
                    new OA\Property(property: 'country', type: 'string', example: 'PT'),
                    new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
                    new OA\Property(property: 'timezone', type: 'string', example: 'Europe/Lisbon'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'organizer_id', type: 'string', format: 'uuid', description: 'Required when called as super_admin'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Property created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StorePropertyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $property = $this->createProperty->execute($validated);

        return response()->json($property, Response::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/properties/{id}',
        summary: 'Get a property by ID',
        security: [['bearerAuth' => []]],
        tags: ['Properties'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Property details'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(string $id): JsonResponse
    {
        $property = $this->properties->findById($id);

        if (!$property || !$this->tenantCanAccess($property)) {
            return response()->json(['message' => 'Property not found.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($property);
    }

    #[OA\Put(
        path: '/properties/{id}',
        summary: 'Update a property',
        security: [['bearerAuth' => []]],
        tags: ['Properties'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Property updated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function update(UpdatePropertyRequest $request, string $id): JsonResponse
    {
        $property = $this->properties->findById($id);

        if (!$property || !$this->tenantCanAccess($property)) {
            return response()->json(['message' => 'Property not found.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();

        return response()->json($this->updateProperty->execute($property, $validated));
    }

    #[OA\Delete(
        path: '/properties/{id}',
        summary: 'Delete a property',
        security: [['bearerAuth' => []]],
        tags: ['Properties'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 204, description: 'Property deleted'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(string $id): JsonResponse
    {
        $property = $this->properties->findById($id);

        if (!$property || !$this->tenantCanAccess($property)) {
            return response()->json(['message' => 'Property not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->properties->delete($property);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function tenantCanAccess(Property $property): bool
    {
        $organizerId = $this->tenantContext->getOrganizerId();
        return $organizerId === null || $property->organizer_id === $organizerId;
    }
}
