<?php

namespace App\Http\Middleware;

use App\Models\IntegrationToken;
use App\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class ValidateIntegrationToken
{
    public function __construct(private TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            return response()->json(['message' => 'Integration token required.'], Response::HTTP_UNAUTHORIZED);
        }

        $token = IntegrationToken::query()
            ->where('is_active', true)
            ->whereNull('expires_at')
            ->orWhere('expires_at', '>', now())
            ->get()
            ->first(fn ($t) => Hash::check($bearerToken, $t->token_hash));

        if (!$token) {
            return response()->json(['message' => 'Invalid integration token.'], Response::HTTP_UNAUTHORIZED);
        }

        $token->update(['last_used_at' => now()]);
        $this->tenantContext->setOrganizerId($token->organizer_id);

        return $next($request);
    }
}
