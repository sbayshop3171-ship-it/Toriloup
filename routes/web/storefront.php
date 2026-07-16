<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['installed', 'identifySurface', 'resolveTenantFromHost', 'ensureTenantResolved', 'ensureTenantActive', 'setTenantContext'])
    ->group(function () {
        Route::get('/_tenant/up', function (Request $request) {
            $tenant = $request->attributes->get(config('tenancy.tenant_request_attribute', 'saas.tenant'));
            $tenantDomain = $request->attributes->get(config('tenancy.tenant_domain_attribute', 'saas.tenant_domain'));

            return response()->json([
                'status' => true,
                'surface' => $request->attributes->get(config('tenancy.surface_request_attribute', 'saas.surface')),
                'tenant' => $tenant?->only(['id', 'name', 'slug', 'status']),
                'domain' => $tenantDomain?->only(['hostname', 'domain_type', 'is_primary', 'is_fallback']),
                'scaffold' => 'storefront-web',
            ]);
        })->name('storefront.web.up');
    });
