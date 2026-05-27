<?php

namespace App\Http\Middleware;

use App\Models\Organizer;
use App\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeStaffRoleUpdate
{
    public function __construct(private TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $authUser = auth('api')->user();

        if (!$authUser) {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($authUser->hasRole('super_admin')) {
            return $next($request);
        }

        if (!$authUser->hasRole('organizer_admin')) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        // When a target user is specified, organizer admin may only act on their own staff.
        $targetUserId = $request->route('userId');

        if ($targetUserId) {
            $organizerId = $this->tenantContext->getOrganizerId();
            $organizer = Organizer::find($organizerId);
            if (!$organizer || !$organizer->users()->where('users.id', $targetUserId)->exists()) {
                return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }
}
