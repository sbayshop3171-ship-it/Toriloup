<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantAttribute = config('tenancy.tenant_request_attribute', 'saas.tenant');
        $domainAttribute = config('tenancy.tenant_domain_attribute', 'saas.tenant_domain');

        $tenant = $request->attributes->get($tenantAttribute);
        $tenantDomain = $request->attributes->get($domainAttribute);

        app()->forgetInstance('currentTenant');
        app()->forgetInstance('currentTenantDomain');

        if ($tenant !== null) {
            app()->instance('currentTenant', $tenant);
        }

        if ($tenantDomain !== null) {
            app()->instance('currentTenantDomain', $tenantDomain);
        }

        return $next($request);
    }
}
