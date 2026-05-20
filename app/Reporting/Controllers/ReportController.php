<?php

namespace App\Reporting\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ReportController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    #[OA\Get(path: '/reports/occupancy', summary: 'Occupancy report', security: [['bearerAuth' => []]], tags: ['Reporting'],
        parameters: [
            new OA\Parameter(name: 'property_id', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'start_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OA\Response(response: 200, description: 'Occupancy data')])]
    public function occupancy(Request $request): JsonResponse
    {
        $request->validate(['start_date' => 'required|date', 'end_date' => 'required|date|after:start_date']);

        $query = Booking::whereNotIn('status', ['cancelled', 'no_show'])
            ->where('check_in_date', '<=', $request->end_date)
            ->where('check_out_date', '>=', $request->start_date);

        if ($request->property_id) {
            $query->where('property_id', $request->property_id);
        }

        $bookings = $query->get();

        return response()->json([
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'total_bookings' => $bookings->count(),
            'total_nights' => $bookings->sum('nights'),
            'total_guests' => $bookings->sum('guests'),
            'by_status' => $bookings->groupBy('status')->map->count(),
        ]);
    }

    #[OA\Get(path: '/reports/revenue', summary: 'Revenue report', security: [['bearerAuth' => []]], tags: ['Reporting'],
        parameters: [
            new OA\Parameter(name: 'property_id', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'start_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OA\Response(response: 200, description: 'Revenue data')])]
    public function revenue(Request $request): JsonResponse
    {
        $request->validate(['start_date' => 'required|date', 'end_date' => 'required|date|after:start_date']);

        $query = Payment::where('status', 'completed')
            ->whereBetween('paid_at', [$request->start_date, $request->end_date . ' 23:59:59']);

        if ($request->property_id) {
            $query->whereHas('booking', fn ($q) => $q->where('property_id', $request->property_id));
        }

        $payments = $query->get();

        return response()->json([
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'total_revenue' => $payments->sum('amount'),
            'by_currency' => $payments->groupBy('currency')->map->sum('amount'),
            'by_method' => $payments->groupBy('method')->map->count(),
        ]);
    }

    #[OA\Get(path: '/reports/bookings', summary: 'Booking statistics', security: [['bearerAuth' => []]], tags: ['Reporting'],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OA\Response(response: 200, description: 'Booking stats')])]
    public function bookingStats(Request $request): JsonResponse
    {
        $request->validate(['start_date' => 'required|date', 'end_date' => 'required|date|after:start_date']);

        $bookings = Booking::whereBetween('created_at', [$request->start_date, $request->end_date . ' 23:59:59'])->get();

        return response()->json([
            'total' => $bookings->count(),
            'by_status' => $bookings->groupBy('status')->map->count(),
            'by_source' => $bookings->groupBy('source')->map->count(),
            'average_nights' => round($bookings->avg('nights'), 1),
            'average_guests' => round($bookings->avg('guests'), 1),
        ]);
    }

    #[OA\Get(path: '/reports/customers', summary: 'Customer statistics', security: [['bearerAuth' => []]], tags: ['Reporting'],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OA\Response(response: 200, description: 'Customer stats')])]
    public function customerStats(Request $request): JsonResponse
    {
        $request->validate(['start_date' => 'required|date', 'end_date' => 'required|date|after:start_date']);

        $newCustomers = Customer::whereBetween('created_at', [$request->start_date, $request->end_date . ' 23:59:59'])->count();

        $topNationalities = Customer::select('nationality', DB::raw('count(*) as total'))
            ->whereNotNull('nationality')
            ->groupBy('nationality')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return response()->json([
            'new_customers' => $newCustomers,
            'top_nationalities' => $topNationalities,
            'total_customers' => Customer::count(),
        ]);
    }
}
