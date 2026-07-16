<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantResolved
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantAttribute = config('tenancy.tenant_request_attribute', 'saas.tenant');
        $tenant = $request->attributes->get($tenantAttribute);

        if ($tenant === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Tenant could not be resolved for this host.'], 404)
                : abort(404);
        }

        return $next($request);
    }
}
