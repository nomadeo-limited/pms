<?php

namespace App\Tax\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TaxRate;
use App\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TaxRateController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['property_id' => 'required|uuid|exists:properties,id']);

        return response()->json(
            TaxRate::where('property_id', $request->property_id)
                ->orderBy('name')
                ->paginate($request->integer('per_page', 50))
        );
    }

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
