<?php

namespace App\Customer\Controllers;

use App\Customer\Requests\StoreReviewRequest;
use App\Customer\Requests\UpdateReviewRequest;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class ReviewController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    #[OA\Get(path: '/reviews', summary: 'List reviews', security: [['bearerAuth' => []]], tags: ['Customers'],
        parameters: [new OA\Parameter(name: 'published_only', in: 'query', schema: new OA\Schema(type: 'boolean'))],
        responses: [new OA\Response(response: 200, description: 'Reviews list')])]
    public function index(Request $request): JsonResponse
    {
        $query = Review::with(['customer', 'booking']);
        if ($request->boolean('published_only')) {
            $query->where('is_published', true);
        }
        return response()->json($query->latest()->paginate($request->integer('per_page', 15)));
    }

    #[OA\Post(path: '/reviews', summary: 'Create a review', security: [['bearerAuth' => []]], tags: ['Customers'],
        requestBody: new OA\RequestBody(required: true,
            content: new OA\JsonContent(required: ['booking_id', 'customer_id', 'overall_rating'],
                properties: [
                    new OA\Property(property: 'booking_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'customer_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'overall_rating', type: 'integer', minimum: 1, maximum: 5),
                    new OA\Property(property: 'accommodation_rating', type: 'integer', minimum: 1, maximum: 5),
                    new OA\Property(property: 'program_rating', type: 'integer', minimum: 1, maximum: 5),
                    new OA\Property(property: 'comment', type: 'string'),
                ])),
        responses: [new OA\Response(response: 201, description: 'Created')])]
    public function store(StoreReviewRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['organizer_id'] = $this->tenantContext->getOrganizerId();

        return response()->json(Review::create($validated), Response::HTTP_CREATED);
    }

    #[OA\Put(path: '/reviews/{id}', summary: 'Update a review', security: [['bearerAuth' => []]], tags: ['Customers'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Updated'), new OA\Response(response: 404, description: 'Not found')])]
    public function update(UpdateReviewRequest $request, string $id): JsonResponse
    {
        $review = Review::find($id);
        if (!$review) {
            return response()->json(['message' => 'Review not found.'], Response::HTTP_NOT_FOUND);
        }
        $review->update($request->validated());
        return response()->json($review->fresh());
    }
}
