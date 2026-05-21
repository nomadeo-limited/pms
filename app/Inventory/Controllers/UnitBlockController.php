<?php

namespace App\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UnitBlock;
use App\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UnitBlockController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'property_id' => 'required|uuid',
        ]);

        $perPage = $request->integer('per_page', 15);

        $blocks = UnitBlock::where('property_id', $request->property_id)
            ->with(['unit:id,name'])
            ->orderBy('start_date')
            ->paginate($perPage);

        return response()->json($blocks);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unit_id'    => 'required|uuid|exists:units,id',
            'property_id' => 'required|uuid|exists:properties,id',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'nullable|string',
            'is_active'  => 'sometimes|boolean',
        ]);

        $validated['organizer_id'] = $this->tenantContext->getOrganizerId();

        $block = UnitBlock::create($validated);

        return response()->json($block->load('unit:id,name'), Response::HTTP_CREATED);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $block = UnitBlock::find($id);
        if (!$block) {
            return response()->json(['message' => 'Maintenance block not found.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'unit_id'    => 'sometimes|uuid|exists:units,id',
            'property_id' => 'sometimes|uuid|exists:properties,id',
            'start_date' => 'sometimes|date',
            'end_date'   => 'sometimes|date|after_or_equal:start_date',
            'reason'     => 'nullable|string',
            'is_active'  => 'sometimes|boolean',
        ]);

        $block->update($validated);

        return response()->json($block->fresh()->load('unit:id,name'));
    }

    public function destroy(string $id): JsonResponse
    {
        $block = UnitBlock::find($id);
        if (!$block) {
            return response()->json(['message' => 'Maintenance block not found.'], Response::HTTP_NOT_FOUND);
        }

        $block->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
