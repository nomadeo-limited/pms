<?php

namespace App\Organizer\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Organizer\Repositories\OrganizerRepositoryInterface;
use App\Organizer\Requests\StoreOrganizerRequest;
use App\Organizer\Requests\UpdateOrganizerRequest;
use App\Organizer\UseCases\CreateOrganizerUseCase;
use App\Organizer\UseCases\UpdateOrganizerUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class OrganizerController extends Controller
{
    public function __construct(
        private readonly OrganizerRepositoryInterface $organizers,
        private readonly CreateOrganizerUseCase $createOrganizer,
        private readonly UpdateOrganizerUseCase $updateOrganizer,
    ) {}

    #[OA\Get(
        path: '/organizers',
        summary: 'List all organizers',
        security: [['bearerAuth' => []]],
        tags: ['Organizers'],
        parameters: [new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15))],
        responses: [
            new OA\Response(response: 200, description: 'Paginated organizers list'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Organizer::class);

        return response()->json(
            $this->organizers->paginate($request->integer('per_page', 15))
        );
    }

    #[OA\Post(
        path: '/organizers',
        summary: 'Create a new organizer',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Surf Paradise Camp'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'phone', type: 'string'),
                    new OA\Property(property: 'country', type: 'string', example: 'PT'),
                    new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
                    new OA\Property(property: 'timezone', type: 'string', example: 'Europe/Lisbon'),
                    new OA\Property(property: 'locale', type: 'string', example: 'en'),
                ]
            )
        ),
        tags: ['Organizers'],
        responses: [
            new OA\Response(response: 201, description: 'Organizer created'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreOrganizerRequest $request): JsonResponse
    {
        $this->authorize('create', Organizer::class);

        $validated = $request->validated();

        $organizer = $this->createOrganizer->execute($validated);

        return response()->json($organizer, Response::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/organizers/{id}',
        summary: 'Get an organizer by ID',
        security: [['bearerAuth' => []]],
        tags: ['Organizers'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Organizer details'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(string $id): JsonResponse
    {
        $organizer = $this->organizers->findById($id);

        if (!$organizer) {
            return response()->json(['message' => 'Organizer not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->authorize('view', $organizer);

        return response()->json($organizer);
    }

    #[OA\Put(
        path: '/organizers/{id}',
        summary: 'Update an organizer',
        security: [['bearerAuth' => []]],
        tags: ['Organizers'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Organizer updated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function update(UpdateOrganizerRequest $request, string $id): JsonResponse
    {
        $organizer = $this->organizers->findById($id);

        if (!$organizer) {
            return response()->json(['message' => 'Organizer not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->authorize('update', $organizer);

        $validated = $request->validated();

        return response()->json($this->updateOrganizer->execute($organizer, $validated));
    }

    #[OA\Delete(
        path: '/organizers/{id}',
        summary: 'Delete an organizer',
        security: [['bearerAuth' => []]],
        tags: ['Organizers'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 204, description: 'Organizer deleted'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(string $id): JsonResponse
    {
        $organizer = $this->organizers->findById($id);

        if (!$organizer) {
            return response()->json(['message' => 'Organizer not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->authorize('delete', $organizer);
        $this->organizers->delete($organizer);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
