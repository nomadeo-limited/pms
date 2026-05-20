<?php

namespace App\Http\Middleware;

use App\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class ResolveTenant
{
    public function __construct(private TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        if (!$user) {
            return $next($request);
        }

        if ($user->hasRole('super_admin')) {
            $overrideId = $request->header('X-Organizer-ID');
            if ($overrideId) {
                $this->tenantContext->setOrganizerId($overrideId);
            }
            return $next($request);
        }

        $payload = JWTAuth::parseToken()->getPayload();
        $organizerId = $payload->get('organizer_id');

        if ($organizerId) {
            $this->tenantContext->setOrganizerId($organizerId);
        }

        return $next($request);
    }
}
