<?php

namespace App\Staff\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\User;
use App\Staff\Requests\InviteStaffRequest;
use App\Staff\Requests\UpdateStaffPasswordRequest;
use App\Staff\Requests\UpdateStaffRoleRequest;
use App\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class StaffController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    #[OA\Get(path: '/staff', summary: 'List staff members', security: [['bearerAuth' => []]], tags: ['Staff'],
        responses: [new OA\Response(response: 200, description: 'Staff list')])]
    public function index(Request $request): JsonResponse
    {
        $organizerId = $this->tenantContext->getOrganizerId();
        if (!$organizerId) {
            return response()->json(['message' => 'Organizer context required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $organizer = Organizer::find($organizerId);
        $staff = $organizer->users()->with('roles')->paginate($request->integer('per_page', 15));

        return response()->json($staff);
    }

    #[OA\Post(path: '/staff/invite', summary: 'Invite a staff member', security: [['bearerAuth' => []]], requestBody: new OA\RequestBody(required: true,
        content: new OA\JsonContent(required: ['name', 'email', 'role'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'role', type: 'string', enum: ['organizer_admin', 'organizer_staff']),
            ])),
        tags: ['Staff'],
        responses: [
            new OA\Response(response: 201, description: 'Staff member invited'),
            new OA\Response(response: 422, description: 'Validation error'),
        ])]
    public function invite(InviteStaffRequest $request): JsonResponse
    {
        $organizerId = $this->tenantContext->getOrganizerId();

        $validated = $request->validated();

        $plainToken = \Illuminate\Support\Str::random(64);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make(\Illuminate\Support\Str::random(32)),
            'is_active' => false,
            'invite_token' => hash('sha256', $plainToken),
            'invite_token_expires_at' => now()->addHours(72),
        ]);
        $organizer = Organizer::find($organizerId);


        $organizer->users()->attach($user->id);
        $user->assignRole($validated['role']);

        return response()->json([
            ...$user->load('roles')->toArray(),
            'invite_token' => $plainToken,
            'invite_expires_at' => $user->invite_token_expires_at,
        ], Response::HTTP_CREATED);
    }

    #[OA\Put(path: '/staff/{userId}/role',
        summary: 'Update staff role',
        security: [['bearerAuth' => []]],
        requestBody:
            new OA\RequestBody(
                required: true,
                content:
                    new OA\JsonContent(
                        required: ['role'],
                        properties: [
                            new OA\Property(
                                property: 'role',
                                type: 'string',
                                enum: ['organizer_admin', 'organizer_staff'])]
                    )
            ),
        tags: ['Staff'],
        parameters: [new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Role updated'), new OA\Response(response: 404, description: 'Not found')])]
    public function updateRole(UpdateStaffRoleRequest $request, string $userId): JsonResponse
    {
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        $user->syncRoles([$request->role]);

        return response()->json($user->load('roles'));
    }

    #[OA\Put(path: '/staff/{userId}/password',
        summary: 'Update staff password',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true,
            content: new OA\JsonContent(required: ['password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ])),
        tags: ['Staff'],
        parameters: [new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 204, description: 'Password updated'), new OA\Response(response: 404, description: 'Not found')])]
    public function updatePassword(UpdateStaffPasswordRequest $request, string $userId): JsonResponse
    {
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        $user->password = $request->password;
        $user->save();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    #[OA\Delete(path: '/staff/{userId}', summary: 'Remove a staff member', security: [['bearerAuth' => []]], tags: ['Staff'],
        parameters: [new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 204, description: 'Removed')])]
    public function destroy(string $userId): JsonResponse
    {
        $organizerId = $this->tenantContext->getOrganizerId();
        $organizer = Organizer::find($organizerId);

        if ($organizer) {
            $organizer->users()->detach($userId);
        }

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
