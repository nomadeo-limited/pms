<?php

namespace App\Program\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AddOn;
use App\Models\Program;
use App\Program\Requests\StoreAddOnRequest;
use App\Program\Requests\UpdateAddOnRequest;
use App\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class AddOnController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    #[OA\Get(path: '/add-ons', summary: 'List add-ons', security: [['bearerAuth' => []]], tags: ['Programs'],
        parameters: [new OA\Parameter(name: 'property_id', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Add-ons list')])]
    public function index(Request $request): JsonResponse
    {
        $query = AddOn::query();
        if ($request->property_id) {
            $query->where('property_id', $request->property_id);
        }
        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    #[OA\Post(path: '/add-ons', summary: 'Create an add-on', security: [['bearerAuth' => []]], requestBody: new OA\RequestBody(required: true,
        content: new OA\JsonContent(required: ['property_id', 'name'],
            properties: [
                new OA\Property(property: 'property_id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'category', type: 'string', enum: ['surf_class', 'yoga_class', 'excursion', 'equipment_rental', 'transfer', 'massage', 'other']),
                new OA\Property(property: 'price', type: 'number', format: 'float'),
                new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
            ])),
        tags: ['Programs'],
        responses: [new OA\Response(response: 201, description: 'Created')])]
    public function store(StoreAddOnRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['organizer_id'] = $this->tenantContext->getOrganizerId();

        return response()->json(AddOn::create($validated), Response::HTTP_CREATED);
    }

    #[OA\Get(path: '/add-ons/{id}', summary: 'Get an add-on', security: [['bearerAuth' => []]], tags: ['Programs'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Add-on'), new OA\Response(response: 404, description: 'Not found')])]
    public function show(string $id): JsonResponse
    {
        $addOn = AddOn::find($id);
        if (!$addOn) {
            return response()->json(['message' => 'Add-on not found.'], Response::HTTP_NOT_FOUND);
        }
        return response()->json($addOn);
    }

    #[OA\Put(path: '/add-ons/{id}', summary: 'Update an add-on', security: [['bearerAuth' => []]], tags: ['Programs'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Updated'), new OA\Response(response: 404, description: 'Not found')])]
    public function update(UpdateAddOnRequest $request, string $id): JsonResponse
    {
        $addOn = AddOn::find($id);
        if (!$addOn) {
            return response()->json(['message' => 'Add-on not found.'], Response::HTTP_NOT_FOUND);
        }
        $addOn->update($request->validated());
        return response()->json($addOn->fresh());
    }

    #[OA\Delete(path: '/add-ons/{id}', summary: 'Delete an add-on', security: [['bearerAuth' => []]], tags: ['Programs'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 204, description: 'Deleted'), new OA\Response(response: 404, description: 'Not found')])]
    public function destroy(string $id): JsonResponse
    {
        $addOn = AddOn::find($id);
        if (!$addOn) {
            return response()->json(['message' => 'Add-on not found.'], Response::HTTP_NOT_FOUND);
        }
        $addOn->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    #[OA\Post(path: '/programs/{programId}/add-ons/{addOnId}', summary: 'Attach add-on to program', security: [['bearerAuth' => []]], tags: ['Programs'],
        parameters: [
            new OA\Parameter(name: 'programId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'addOnId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [new OA\Response(response: 200, description: 'Attached'), new OA\Response(response: 404, description: 'Not found')])]
    public function attach(Request $request, string $programId, string $addOnId): JsonResponse
    {
        $program = Program::find($programId);
        $addOn = AddOn::find($addOnId);

        if (!$program || !$addOn) {
            return response()->json(['message' => 'Program or add-on not found.'], Response::HTTP_NOT_FOUND);
        }

        $program->addOns()->syncWithoutDetaching([
            $addOnId => ['is_default' => $request->boolean('is_default')],
        ]);

        return response()->json($program->load('addOns'));
    }

    #[OA\Delete(path: '/programs/{programId}/add-ons/{addOnId}', summary: 'Detach add-on from program', security: [['bearerAuth' => []]], tags: ['Programs'],
        parameters: [
            new OA\Parameter(name: 'programId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'addOnId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [new OA\Response(response: 204, description: 'Detached')])]
    public function detach(string $programId, string $addOnId): JsonResponse
    {
        $program = Program::find($programId);
        if ($program) {
            $program->addOns()->detach($addOnId);
        }
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
