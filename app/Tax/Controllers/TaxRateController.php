<?php

namespace App\Tax\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TaxRate;
use App\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class TaxRateController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    #[OA\Get(
        path: '/tax-rates',
        summary: 'List tax rates for a property',
        security: [['bearerAuth' => []]],
        tags: ['Tax Rates'],
        parameters: [
            new OA\Parameter(name: 'property_id', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of tax rates'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $request->validate(['property_id' => 'required|uuid|exists:properties,id']);

        return response()->json(
            TaxRate::where('property_id', $request->property_id)
                ->orderBy('name')
                ->paginate($request->integer('per_page', 50))
        );
    }

    #[OA\Post(
        path: '/tax-rates',
        summary: 'Create a tax rate',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['property_id', 'name', 'rate'],
                properties: [
                    new OA\Property(property: 'property_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'name', type: 'string', maxLength: 100),
                    new OA\Property(property: 'rate', type: 'number', format: 'float', minimum: 0, maximum: 100),
                    new OA\Property(property: 'applies_to', type: 'string', enum: ['accommodation', 'add_on', 'all']),
                    new OA\Property(property: 'is_inclusive', type: 'boolean'),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                ]
            )
        ),
        tags: ['Tax Rates'],
        responses: [
            new OA\Response(response: 201, description: 'Tax rate created'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'property_id' => 'required|uuid|exists:properties,id',
            'name'        => 'required|string|max:100',
            'rate'        => 'required|numeric|min:0|max:100',
            'applies_to'  => 'sometimes|in:accommodation,add_on,all',
            'is_inclusive' => 'sometimes|boolean',
            'is_active'   => 'sometimes|boolean',
        ]);

        $validated['organizer_id'] = $this->tenantContext->getOrganizerId();

        return response()->json(TaxRate::create($validated), Response::HTTP_CREATED);
    }

    #[OA\Put(
        path: '/tax-rates/{id}',
        summary: 'Update a tax rate',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 100),
                    new OA\Property(property: 'rate', type: 'number', format: 'float', minimum: 0, maximum: 100),
                    new OA\Property(property: 'applies_to', type: 'string', enum: ['accommodation', 'add_on', 'all']),
                    new OA\Property(property: 'is_inclusive', type: 'boolean'),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                ]
            )
        ),
        tags: ['Tax Rates'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tax rate updated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        $taxRate = TaxRate::find($id);
        if (!$taxRate) {
            return response()->json(['message' => 'Tax rate not found.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'rate'        => 'sometimes|numeric|min:0|max:100',
            'applies_to'  => 'sometimes|in:accommodation,add_on,all',
            'is_inclusive' => 'sometimes|boolean',
            'is_active'   => 'sometimes|boolean',
        ]);

        $taxRate->update($validated);

        return response()->json($taxRate->fresh());
    }

    #[OA\Delete(
        path: '/tax-rates/{id}',
        summary: 'Delete a tax rate',
        security: [['bearerAuth' => []]],
        tags: ['Tax Rates'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(string $id): JsonResponse
    {
        $taxRate = TaxRate::find($id);
        if (!$taxRate) {
            return response()->json(['message' => 'Tax rate not found.'], Response::HTTP_NOT_FOUND);
        }

        $taxRate->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
