<?php

namespace App\Http\Middleware;

use App\Services\Saas\SubscriptionManagerService;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantFeatureAccess
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SubscriptionManagerService $subscriptionManagerService,
    ) {
    }

    public function handle(Request $request, Closure $next, string $featureCode): Response
    {
        $tenant = $this->tenantContext->current();

        if ($tenant === null || $this->subscriptionManagerService->hasFeatureAccess($tenant, $featureCode)) {
            return $next($request);
        }

        return response()->json([
            'status' => false,
            'code' => 'upgrade_required',
            'feature_code' => $featureCode,
            'message' => 'This feature requires an upgraded plan.',
            'upgrade_url' => sprintf('https://%s/admin/settings/billing', trim((string) config('saas.merchant_host'), '.')),
        ], 402);
    }
}
