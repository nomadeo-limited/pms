<?php

namespace App\Auth\Controllers;

use App\Auth\Requests\AcceptInviteRequest;
use App\Auth\Requests\LoginRequest;
use App\Auth\UseCases\LoginUseCase;
use App\Auth\UseCases\RefreshTokenUseCase;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private LoginUseCase $loginUseCase,
        private RefreshTokenUseCase $refreshTokenUseCase,
    ) {}

    #[OA\Post(
        path: '/auth/login',
        summary: 'Login and receive a JWT token',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@nomadeo.es'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secret'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful login',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'access_token', type: 'string'),
                        new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
                        new OA\Property(property: 'expires_in', type: 'integer', example: 86400),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Invalid credentials'),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $token = $this->loginUseCase->execute($request->email, $request->password);
        } catch (AuthenticationException) {
            return response()->json(['message' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->tokenResponse($token);
    }

    #[OA\Post(
        path: '/auth/logout',
        summary: 'Logout and invalidate the token',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Logged out'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json(['message' => 'Successfully logged out.']);
    }

    #[OA\Post(
        path: '/auth/refresh',
        summary: 'Refresh the JWT token',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'New token issued'),
            new OA\Response(response: 401, description: 'Token invalid or expired'),
        ]
    )]
    public function refresh(): JsonResponse
    {
        return $this->tokenResponse($this->refreshTokenUseCase->execute());
    }

    #[OA\Get(
        path: '/auth/me',
        summary: 'Get the authenticated user',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Authenticated user'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function me(): JsonResponse
    {
        return response()->json(auth('api')->user()->load('roles'));
    }

    #[OA\Post(
        path: '/auth/accept-invite',
        summary: 'Set password and activate account using an invite token',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'token', type: 'string', example: 'abc123...'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Account activated, JWT returned'),
            new OA\Response(response: 422, description: 'Invalid or expired invite token'),
        ]
    )]
    public function acceptInvite(AcceptInviteRequest $request): JsonResponse
    {
        $user = User::where('invite_token', hash('sha256', $request->token))
            ->where('invite_token_expires_at', '>', now())
            ->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid or expired invite token.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'is_active' => true,
            'invite_token' => null,
            'invite_token_expires_at' => null,
        ]);

        $token = auth('api')->login($user);

        return $this->tokenResponse($token);
    }

    private function tokenResponse(string $token): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ]);
    }
}
