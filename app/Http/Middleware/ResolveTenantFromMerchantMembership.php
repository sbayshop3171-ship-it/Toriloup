<?php

namespace App\Http\Middleware;

use App\Models\TenantMember;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantFromMerchantMembership
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $tenant = $this->tenantContext->hydrateFromRequest($request);

        if ($tenant === null) {
            $tenantSlug = trim((string) ($request->headers->get('X-Tenant-Slug') ?: $request->query('tenant_slug', '')));

            $membership = TenantMember::query()
                ->with(['tenant.domains' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('is_fallback')])
                ->where('user_id', $user->getKey())
                ->where('status', 'active');

            if ($tenantSlug !== '') {
                $membership->whereHas('tenant', fn ($query) => $query->where('slug', $tenantSlug));
            }

            $tenantMember = $membership->orderBy('id')->first();

            if ($tenantMember?->tenant !== null) {
                $domain = $tenantMember->tenant->domains->first();
                $this->tenantContext->set($tenantMember->tenant, $domain, $request);
                $tenant = $tenantMember->tenant;
            }
        }

        if ($tenant === null) {
            return response()->json(['message' => 'No active merchant tenant context found.'], 403);
        }

        return $next($request);
    }
}
