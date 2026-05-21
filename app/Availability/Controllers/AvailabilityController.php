<?php

namespace App\Availability\Controllers;

use App\Availability\Helpers\WeekdayMask;
use App\Availability\Requests\StoreAvailabilityRuleRequest;
use App\Availability\Requests\StoreBookingRuleRequest;
use App\Availability\UseCases\CheckAvailabilityUseCase;
use App\Availability\UseCases\GetAvailabilityCalendarUseCase;
use App\Http\Controllers\Controller;
use App\Models\AvailabilityRule;
use App\Models\BookingRule;
use App\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class AvailabilityController extends Controller
{
    public function __construct(
        private TenantContext $tenantContext,
        private GetAvailabilityCalendarUseCase $calendarUseCase,
        private CheckAvailabilityUseCase $checkUseCase,
    ) {}

    #[OA\Get(path: '/availability', summary: 'Get per-day availability calendar for a property', security: [['bearerAuth' => []]], tags: ['Availability'],
        parameters: [
            new OA\Parameter(name: 'property_id', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'start_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OA\Response(response: 200, description: 'Per-day availability broken down by unit counts')])]
    public function calendar(Request $request): JsonResponse
    {
        $request->validate([
            'property_id' => 'required|uuid|exists:properties,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        return response()->json($this->calendarUseCase->execute(
            $request->property_id,
            $request->start_date,
            $request->end_date,
        ));
    }

    #[OA\Get(path: '/availability/check', summary: 'Check if a date range has available units', security: [['bearerAuth' => []]], tags: ['Availability'],
        parameters: [
            new OA\Parameter(name: 'property_id', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'check_in_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'check_out_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'min_units', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [new OA\Response(response: 200, description: 'Availability check result')])]
    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'property_id' => 'required|uuid|exists:properties,id',
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after:check_in_date',
            'min_units' => 'nullable|integer|min:1',
        ]);

        return response()->json($this->checkUseCase->execute(
            $request->property_id,
            $request->check_in_date,
            $request->check_out_date,
            $request->integer('min_units', 1),
        ));
    }

    #[OA\Get(path: '/availability/rules', summary: 'List availability rules', security: [['bearerAuth' => []]], tags: ['Availability'],
        responses: [new OA\Response(response: 200, description: 'Rules list')])]
    public function indexRules(Request $request): JsonResponse
    {
        $paginated = AvailabilityRule::paginate($request->integer('per_page', 15));
        $paginated->getCollection()->transform(fn($r) => $this->formatRule($r));
        return response()->json($paginated);
    }

    #[OA\Post(path: '/availability/rules', summary: 'Create an availability rule', security: [['bearerAuth' => []]], tags: ['Availability'],
        requestBody: new OA\RequestBody(required: true,
            content: new OA\JsonContent(required: ['ruleable_type', 'ruleable_id', 'rule_type'],
                properties: [
                    new OA\Property(property: 'ruleable_type', type: 'string', enum: ['program', 'unit', 'room_type']),
                    new OA\Property(property: 'ruleable_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'rule_type', type: 'string', enum: ['daily', 'specific_dates', 'date_range']),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'weekday_mask', type: 'array', items: new OA\Items(type: 'string'), example: ['monday', 'tuesday']),
                    new OA\Property(property: 'capacity', type: 'integer'),
                ])),
        responses: [new OA\Response(response: 201, description: 'Created')])]
    public function storeRule(StoreAvailabilityRuleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['organizer_id'] = $this->tenantContext->getOrganizerId();

        $rule = AvailabilityRule::create($validated);
        return response()->json($this->formatRule($rule), Response::HTTP_CREATED);
    }

    #[OA\Delete(path: '/availability/rules/{id}', summary: 'Delete an availability rule', security: [['bearerAuth' => []]], tags: ['Availability'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 204, description: 'Deleted'), new OA\Response(response: 404, description: 'Not found')])]
    public function destroyRule(string $id): JsonResponse
    {
        $rule = AvailabilityRule::find($id);
        if (!$rule) {
            return response()->json(['message' => 'Rule not found.'], Response::HTTP_NOT_FOUND);
        }
        $rule->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    #[OA\Get(path: '/booking-rules', summary: 'List booking rules', security: [['bearerAuth' => []]], tags: ['Availability'],
        responses: [new OA\Response(response: 200, description: 'Booking rules list')])]
    public function indexBookingRules(Request $request): JsonResponse
    {
        $paginated = BookingRule::paginate($request->integer('per_page', 15));
        $paginated->getCollection()->transform(fn($r) => $this->formatBookingRule($r));
        return response()->json($paginated);
    }

    #[OA\Post(path: '/booking-rules', summary: 'Create a booking rule', security: [['bearerAuth' => []]], tags: ['Availability'],
        requestBody: new OA\RequestBody(required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'property_id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'program_id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'min_nights', type: 'integer', example: 3),
                new OA\Property(property: 'max_nights', type: 'integer', example: 28),
                new OA\Property(property: 'check_in_days', type: 'array', items: new OA\Items(type: 'string'), example: ['monday', 'sunday']),
                new OA\Property(property: 'min_advance_days', type: 'integer', example: 1),
                new OA\Property(property: 'max_advance_days', type: 'integer', example: 180),
                new OA\Property(property: 'start_date', type: 'string', format: 'date', description: 'Rule applies only from this date (inclusive)'),
                new OA\Property(property: 'end_date', type: 'string', format: 'date', description: 'Rule applies only until this date (inclusive)'),
            ])),
        responses: [new OA\Response(response: 201, description: 'Created')])]
    public function storeBookingRule(StoreBookingRuleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['organizer_id'] = $this->tenantContext->getOrganizerId();

        $rule = BookingRule::create($validated);
        return response()->json($this->formatBookingRule($rule), Response::HTTP_CREATED);
    }

    private function formatRule(AvailabilityRule $rule): array
    {
        $data = $rule->toArray();
        $data['weekday_mask'] = WeekdayMask::toArray($rule->weekday_mask ?? '1111111');
        return $data;
    }

    private function formatBookingRule(BookingRule $rule): array
    {
        $data = $rule->toArray();
        if ($rule->check_in_days !== null) {
            $data['check_in_days'] = WeekdayMask::toArray($rule->check_in_days);
        }
        if ($rule->check_out_days !== null) {
            $data['check_out_days'] = WeekdayMask::toArray($rule->check_out_days);
        }
        return $data;
    }
}
