<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\Tenancy\TenantResolver;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantFromHost
{
    public function __construct(private readonly TenantResolver $tenantResolver)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $tenantDomain = $this->tenantResolver->resolveFromRequest($request);

        if ($tenantDomain !== null) {
            $tenantAttribute = config('tenancy.tenant_request_attribute', 'saas.tenant');
            $domainAttribute = config('tenancy.tenant_domain_attribute', 'saas.tenant_domain');

            $request->attributes->set($tenantAttribute, $tenantDomain->tenant);
            $request->attributes->set($domainAttribute, $tenantDomain);
        }

        return $next($request);
    }
}
