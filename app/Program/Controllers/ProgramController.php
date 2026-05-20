<?php

namespace App\Program\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Program\Requests\StoreProgramRequest;
use App\Program\Requests\UpdateProgramRequest;
use App\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class ProgramController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    #[OA\Get(path: '/programs', summary: 'List programs', security: [['bearerAuth' => []]], tags: ['Programs'],
        parameters: [new OA\Parameter(name: 'property_id', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Programs list')])]
    public function index(Request $request): JsonResponse
    {
        $query = Program::query();
        if ($request->property_id) {
            $query->where('property_id', $request->property_id);
        }
        return response()->json($query->with('addOns')->paginate($request->integer('per_page', 15)));
    }

    #[OA\Post(path: '/programs', summary: 'Create a program', security: [['bearerAuth' => []]], tags: ['Programs'],
        requestBody: new OA\RequestBody(required: true,
            content: new OA\JsonContent(required: ['property_id', 'name'],
                properties: [
                    new OA\Property(property: 'property_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'room_type_id', type: 'string', format: 'uuid', description: 'Room type used by this program — enables automatic unit assignment on booking'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'type', type: 'string', enum: ['surf_camp', 'yoga_retreat', 'language_immersion', 'diving', 'hiking', 'climbing', 'wellness', 'other']),
                    new OA\Property(property: 'duration_days', type: 'integer'),
                    new OA\Property(property: 'base_price', type: 'number', description: 'Fixed package price — used as total_price when booking without explicit total_price'),
                    new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
                    new OA\Property(property: 'description', type: 'string'),
                ])),
        responses: [new OA\Response(response: 201, description: 'Created')])]
    public function store(StoreProgramRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['organizer_id'] = $this->tenantContext->getOrganizerId();

        return response()->json(Program::create($validated), Response::HTTP_CREATED);
    }

    #[OA\Get(path: '/programs/{id}', summary: 'Get a program', security: [['bearerAuth' => []]], tags: ['Programs'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Program'), new OA\Response(response: 404, description: 'Not found')])]
    public function show(string $id): JsonResponse
    {
        $program = Program::with('addOns')->find($id);
        if (!$program) {
            return response()->json(['message' => 'Program not found.'], Response::HTTP_NOT_FOUND);
        }
        return response()->json($program);
    }

    #[OA\Put(path: '/programs/{id}', summary: 'Update a program', security: [['bearerAuth' => []]], tags: ['Programs'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Updated'), new OA\Response(response: 404, description: 'Not found')])]
    public function update(UpdateProgramRequest $request, string $id): JsonResponse
    {
        $program = Program::find($id);
        if (!$program) {
            return response()->json(['message' => 'Program not found.'], Response::HTTP_NOT_FOUND);
        }

        $program->update($request->validated());

        return response()->json($program->fresh());
    }

    #[OA\Delete(path: '/programs/{id}', summary: 'Delete a program', security: [['bearerAuth' => []]], tags: ['Programs'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 204, description: 'Deleted'), new OA\Response(response: 404, description: 'Not found')])]
    public function destroy(string $id): JsonResponse
    {
        $program = Program::find($id);
        if (!$program) {
            return response()->json(['message' => 'Program not found.'], Response::HTTP_NOT_FOUND);
        }
        $program->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
