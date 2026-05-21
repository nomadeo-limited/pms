<?php

namespace App\Housekeeping\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\UnitHousekeeping;
use App\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HousekeepingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'property_id' => 'required|uuid',
            'date'        => 'required|date',
        ]);

        $propertyId = $request->property_id;
        $date       = $request->date;

        $units = Unit::with('room')
            ->where('property_id', $propertyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $unitIds = $units->pluck('id');

        $housekeepingByUnit = UnitHousekeeping::whereIn('unit_id', $unitIds)
            ->whereDate('date', $date)
            ->get()
            ->keyBy('unit_id');

        $result = $units->map(function (Unit $unit) use ($date, $housekeepingByUnit) {
            $record = $housekeepingByUnit->get($unit->id);
            return [
                'unit_id'    => $unit->id,
                'unit_name'  => $unit->name,
                'room'       => $unit->room?->name ?? null,
                'date'       => $date,
                'status'     => $record?->status ?? 'dirty',
                'notes'      => $record?->notes ?? null,
                'is_blocked' => false,
            ];
        })->values();

        return response()->json($result);
    }

    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unit_id' => 'required|uuid|exists:units,id',
            'date'    => 'required|date',
            'status'  => 'required|in:dirty,clean,inspected,occupied,out_of_service',
            'notes'   => 'nullable|string',
        ]);

        $organizerId = app()->make(TenantContext::class)->getOrganizerId();
        $propertyId  = Unit::find($validated['unit_id'])->property_id;

        $record = UnitHousekeeping::updateOrCreate(
            [
                'unit_id' => $validated['unit_id'],
                'date'    => $validated['date'],
            ],
            [
                'status'       => $validated['status'],
                'notes'        => $validated['notes'] ?? null,
                'property_id'  => $propertyId,
                'organizer_id' => $organizerId,
            ]
        );

        return response()->json($record);
    }
}
