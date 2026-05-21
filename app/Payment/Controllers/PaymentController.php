<?php

namespace App\Payment\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentRule;
use App\Payment\Requests\StorePaymentRequest;
use App\Payment\Requests\StorePaymentRuleRequest;
use App\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    #[OA\Get(path: '/bookings/{bookingId}/payments', summary: 'List payments for a booking', security: [['bearerAuth' => []]], tags: ['Payments'],
        parameters: [
            new OA\Parameter(name: 'bookingId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'sort_by', in: 'query', schema: new OA\Schema(type: 'string', enum: ['due_date', 'paid_at', 'created_at'], default: 'due_date')),
            new OA\Parameter(name: 'sort_dir', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'asc')),
        ],
        responses: [new OA\Response(response: 200, description: 'Payments list'), new OA\Response(response: 404, description: 'Booking not found')])]
    public function index(Request $request, string $bookingId): JsonResponse
    {
        $booking = Booking::find($bookingId);
        if (!$booking) {
            return response()->json(['message' => 'Booking not found.'], Response::HTTP_NOT_FOUND);
        }

        $allowedSorts = ['due_date', 'paid_at', 'created_at'];
        $sortBy = in_array($request->sort_by, $allowedSorts) ? $request->sort_by : 'due_date';
        $sortDir = $request->sort_dir === 'desc' ? 'desc' : 'asc';

        return response()->json($booking->payments()->orderBy($sortBy, $sortDir)->get());
    }

    #[OA\Post(path: '/bookings/{bookingId}/payments', summary: 'Record a payment', security: [['bearerAuth' => []]], tags: ['Payments'],
        parameters: [new OA\Parameter(name: 'bookingId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        requestBody: new OA\RequestBody(required: true,
            content: new OA\JsonContent(required: ['amount', 'currency', 'method'],
                properties: [
                    new OA\Property(property: 'amount', type: 'number', format: 'float', example: 200.00),
                    new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
                    new OA\Property(property: 'method', type: 'string', enum: ['stripe', 'bank_transfer', 'cash', 'other']),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'paid_at', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'notes', type: 'string'),
                ])),
        responses: [new OA\Response(response: 201, description: 'Payment recorded')])]
    public function store(StorePaymentRequest $request, string $bookingId): JsonResponse
    {
        $booking = Booking::find($bookingId);
        if (!$booking) {
            return response()->json(['message' => 'Booking not found.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();

        $validated['booking_id'] = $bookingId;
        $validated['organizer_id'] = $this->tenantContext->getOrganizerId();
        $validated['status'] ??= 'completed';

        if ($validated['status'] === 'completed' && !isset($validated['paid_at'])) {
            $validated['paid_at'] = now();
        }

        $payment = Payment::create($validated);

        $this->updateBookingPaymentStatus($booking);

        return response()->json($payment, Response::HTTP_CREATED);
    }

    private function updateBookingPaymentStatus(Booking $booking): void
    {
        $totalPaid = $booking->payments()->where('status', 'completed')->sum('amount');
        $totalRefunded = $booking->payments()->where('status', 'refunded')->sum('amount');
        $netPaid = $totalPaid - $totalRefunded;

        if ($netPaid <= 0) {
            $status = 'unpaid';
        } elseif ($netPaid < $booking->total_price) {
            $status = 'partial';
        } else {
            $status = 'paid';
        }

        if ($totalRefunded >= $booking->total_price) {
            $status = 'refunded';
        }

        $booking->update(['payment_status' => $status]);
    }

    #[OA\Get(path: '/payment-rules', summary: 'List payment rules', security: [['bearerAuth' => []]], tags: ['Payments'],
        responses: [new OA\Response(response: 200, description: 'Payment rules')])]
    public function indexRules(Request $request): JsonResponse
    {
        return response()->json(PaymentRule::paginate($request->integer('per_page', 15)));
    }

    #[OA\Post(path: '/payment-rules', summary: 'Create a payment rule', security: [['bearerAuth' => []]], tags: ['Payments'],
        requestBody: new OA\RequestBody(required: true,
            content: new OA\JsonContent(required: ['type'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['full_upfront', 'deposit_then_balance', 'installments']),
                    new OA\Property(property: 'property_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'deposit_percentage', type: 'number', format: 'float', example: 30.0),
                    new OA\Property(property: 'balance_due_days_before', type: 'integer', example: 30),
                ])),
        responses: [new OA\Response(response: 201, description: 'Created')])]
    public function storeRule(StorePaymentRuleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['organizer_id'] = $this->tenantContext->getOrganizerId();

        return response()->json(PaymentRule::create($validated), Response::HTTP_CREATED);
    }
}
