<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\Saas\TenantProvisioningService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SeedTenantDemoContent
{
    public function __construct(private readonly TenantProvisioningService $tenantProvisioningService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->attributes->get(config('tenancy.tenant_request_attribute', 'saas.tenant'));

        if ($request->isMethod('GET') && $tenant instanceof Tenant) {
            try {
                $this->tenantProvisioningService->seedStorefrontDefaultsIfNeeded($tenant);
            } catch (Throwable $exception) {
                Log::warning('Tenant demo storefront sync skipped.', [
                    'tenant_id' => $tenant->id,
                    'host' => $request->getHost(),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $next($request);
    }
}
