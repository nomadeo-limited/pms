<?php

namespace App\Customer\Controllers;

use App\Customer\Requests\StoreCustomerRequest;
use App\Customer\Requests\UpdateCustomerRequest;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class CustomerController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    #[OA\Get(path: '/customers', summary: 'List customers', security: [['bearerAuth' => []]], tags: ['Customers'],
        responses: [new OA\Response(response: 200, description: 'Customers list')])]
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query();
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'ilike', "%{$request->search}%")
                    ->orWhere('last_name', 'ilike', "%{$request->search}%")
                    ->orWhere('email', 'ilike', "%{$request->search}%");
            });
        }
        return response()->json($query->orderBy('last_name')->paginate($request->integer('per_page', 15)));
    }

    #[OA\Post(path: '/customers', summary: 'Create a customer', security: [['bearerAuth' => []]], tags: ['Customers'],
        requestBody: new OA\RequestBody(required: true,
            content: new OA\JsonContent(required: ['first_name', 'last_name', 'email'],
                properties: [
                    new OA\Property(property: 'first_name', type: 'string'),
                    new OA\Property(property: 'last_name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'phone', type: 'string'),
                    new OA\Property(property: 'nationality', type: 'string', example: 'PT'),
                ])),
        responses: [new OA\Response(response: 201, description: 'Created'), new OA\Response(response: 422, description: 'Validation error')])]
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['organizer_id'] = $this->tenantContext->getOrganizerId();

        return response()->json(Customer::create($validated), Response::HTTP_CREATED);
    }

    #[OA\Get(path: '/customers/{id}', summary: 'Get a customer', security: [['bearerAuth' => []]], tags: ['Customers'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Customer'), new OA\Response(response: 404, description: 'Not found')])]
    public function show(string $id): JsonResponse
    {
        $customer = Customer::find($id);
        if (!$customer) {
            return response()->json(['message' => 'Customer not found.'], Response::HTTP_NOT_FOUND);
        }
        return response()->json($customer->load('bookings'));
    }

    #[OA\Put(path: '/customers/{id}', summary: 'Update a customer', security: [['bearerAuth' => []]], tags: ['Customers'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Updated'), new OA\Response(response: 404, description: 'Not found')])]
    public function update(UpdateCustomerRequest $request, string $id): JsonResponse
    {
        $customer = Customer::find($id);
        if (!$customer) {
            return response()->json(['message' => 'Customer not found.'], Response::HTTP_NOT_FOUND);
        }

        $customer->update($request->validated());

        return response()->json($customer->fresh());
    }

    #[OA\Delete(path: '/customers/{id}', summary: 'Delete a customer', security: [['bearerAuth' => []]], tags: ['Customers'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 204, description: 'Deleted')])]
    public function destroy(string $id): JsonResponse
    {
        $customer = Customer::find($id);
        if ($customer) $customer->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
