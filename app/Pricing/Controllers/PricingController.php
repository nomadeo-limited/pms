<?php

namespace App\Pricing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\PricingRule;
use App\Models\Unit;
use App\Pricing\Requests\StoreDiscountRequest;
use App\Pricing\Requests\StorePricingRuleRequest;
use App\Pricing\Requests\UpdatePricingRuleRequest;
use App\Pricing\UseCases\CalculatePriceUseCase;
use App\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class PricingController extends Controller
{
    public function __construct(
        private TenantContext $tenantContext,
        private CalculatePriceUseCase $calculatePrice,
    ) {}

    #[OA\Get(path: '/pricing/calculate', summary: 'Calculate price for a priceable item and date range', security: [['bearerAuth' => []]], tags: ['Pricing'],
        parameters: [
            new OA\Parameter(name: 'priceable_type', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['unit', 'program', 'add_on'])),
            new OA\Parameter(name: 'priceable_id', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'check_in_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'check_out_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'guests', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'discount_code', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Price breakdown')])]
    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'priceable_type' => 'required|in:unit,program,add_on',
            'priceable_id' => 'required|uuid',
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after:check_in_date',
            'guests' => 'nullable|integer|min:1',
            'discount_code' => 'nullable|string',
        ]);

        return response()->json($this->calculatePrice->execute(
            $request->priceable_type,
            $request->priceable_id,
            $request->check_in_date,
            $request->check_out_date,
            $request->integer('guests', 1),
            $request->discount_code,
        ));
    }

    #[OA\Get(path: '/pricing-rules', summary: 'List pricing rules', security: [['bearerAuth' => []]], tags: ['Pricing'],
        responses: [new OA\Response(response: 200, description: 'Pricing rules list')])]
    public function indexRules(Request $request): JsonResponse
    {
        return response()->json(PricingRule::where('is_active', true)->paginate($request->integer('per_page', 15)));
    }

    #[OA\Post(path: '/pricing-rules', summary: 'Create a pricing rule', security: [['bearerAuth' => []]], tags: ['Pricing'],
        requestBody: new OA\RequestBody(required: true,
            content: new OA\JsonContent(required: ['priceable_type', 'priceable_id', 'name', 'model', 'amount', 'currency'],
                properties: [
                    new OA\Property(property: 'priceable_type', type: 'string', enum: ['program', 'unit', 'add_on']),
                    new OA\Property(property: 'priceable_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'name', type: 'string', example: 'High Season'),
                    new OA\Property(property: 'model', type: 'string', enum: ['per_night', 'per_person_per_night', 'fixed_package']),
                    new OA\Property(property: 'amount', type: 'number', format: 'float', example: 45.00),
                    new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'priority', type: 'integer', example: 1),
                ])),
        responses: [new OA\Response(response: 201, description: 'Created')])]
    public function storeRule(StorePricingRuleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $organizerId = $this->tenantContext->getOrganizerId();

        if ($validated['priceable_type'] === 'room_type') {
            $units = Unit::where('property_id', $validated['property_id'])
                ->where('room_type_id', $validated['priceable_id'])
                ->where('is_active', true)
                ->get();

            if ($units->isEmpty()) {
                return response()->json(['message' => 'No active units found for this room type in the selected property.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $base = array_diff_key($validated, array_flip(['priceable_type', 'priceable_id', 'property_id']));
            $base['organizer_id'] = $organizerId;
            $base['priceable_type'] = 'unit';

            $created = DB::transaction(function () use ($units, $base) {
                return $units->map(fn($unit) => PricingRule::create(
                    array_merge($base, ['priceable_id' => $unit->id])
                ));
            });

            return response()->json($created, Response::HTTP_CREATED);
        }

        $validated['organizer_id'] = $organizerId;

        return response()->json(PricingRule::create($validated), Response::HTTP_CREATED);
    }

    #[OA\Put(path: '/pricing-rules/{id}', summary: 'Update a pricing rule', security: [['bearerAuth' => []]], tags: ['Pricing'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Updated'), new OA\Response(response: 404, description: 'Not found')])]
    public function updateRule(UpdatePricingRuleRequest $request, string $id): JsonResponse
    {
        $rule = PricingRule::find($id);
        if (!$rule) {
            return response()->json(['message' => 'Pricing rule not found.'], Response::HTTP_NOT_FOUND);
        }
        $rule->update($request->validated());
        return response()->json($rule->fresh());
    }

    #[OA\Delete(path: '/pricing-rules/{id}', summary: 'Delete a pricing rule', security: [['bearerAuth' => []]], tags: ['Pricing'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 204, description: 'Deleted')])]
    public function destroyRule(string $id): JsonResponse
    {
        $rule = PricingRule::find($id);
        if ($rule) $rule->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    #[OA\Get(path: '/discounts', summary: 'List discounts', security: [['bearerAuth' => []]], tags: ['Pricing'],
        responses: [new OA\Response(response: 200, description: 'Discounts list')])]
    public function indexDiscounts(Request $request): JsonResponse
    {
        return response()->json(Discount::paginate($request->integer('per_page', 15)));
    }

    #[OA\Post(path: '/discounts', summary: 'Create a discount', security: [['bearerAuth' => []]], tags: ['Pricing'],
        requestBody: new OA\RequestBody(required: true,
            content: new OA\JsonContent(required: ['type', 'value'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', example: 'SURF20'),
                    new OA\Property(property: 'type', type: 'string', enum: ['percentage', 'fixed_amount', 'early_bird', 'last_minute', 'long_stay']),
                    new OA\Property(property: 'value', type: 'number', format: 'float', example: 20.0),
                    new OA\Property(property: 'min_nights', type: 'integer'),
                    new OA\Property(property: 'max_uses', type: 'integer'),
                    new OA\Property(property: 'valid_from', type: 'string', format: 'date'),
                    new OA\Property(property: 'valid_until', type: 'string', format: 'date'),
                ])),
        responses: [new OA\Response(response: 201, description: 'Created')])]
    public function storeDiscount(StoreDiscountRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['organizer_id'] = $this->tenantContext->getOrganizerId();

        return response()->json(Discount::create($validated), Response::HTTP_CREATED);
    }

    #[OA\Delete(path: '/discounts/{id}', summary: 'Delete a discount', security: [['bearerAuth' => []]], tags: ['Pricing'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 204, description: 'Deleted')])]
    public function destroyDiscount(string $id): JsonResponse
    {
        $discount = Discount::find($id);
        if ($discount) $discount->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
