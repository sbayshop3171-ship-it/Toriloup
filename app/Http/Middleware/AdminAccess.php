<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAccess
{
    private const ADMIN_ROLE_IDS = [
        Role::ADMIN,
        Role::MANAGER,
        Role::POS_OPERATOR,
        Role::STUFF,
    ];

    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    /**
     * Restrict admin endpoints to admin/staff roles only.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $hasAdminRole = $user?->roles()->whereIn('id', self::ADMIN_ROLE_IDS)->exists() ?? false;

        if (!$hasAdminRole) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Admin access only.',
            ], 403);
        }

        $this->tenantContext->hydrateFromRequest($request);

        return $next($request);
    }
}
