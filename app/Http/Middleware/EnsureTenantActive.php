<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantAttribute = config('tenancy.tenant_request_attribute', 'saas.tenant');
        $tenant = $request->attributes->get($tenantAttribute);

        if ($tenant === null) {
            return $next($request);
        }

        $allowedStatuses = config('tenancy.active_tenant_statuses', ['active']);

        if (!in_array((string) $tenant->status, $allowedStatuses, true)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Tenant is not active.'], 423)
                : abort(423);
        }

        return $next($request);
    }
}
