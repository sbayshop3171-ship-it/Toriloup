<?php

use App\Http\Controllers\Saas\StorefrontBootstrapController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('storefront')
    ->name('storefront.')
    ->middleware(['installed', 'apiKey', 'localization', 'identifySurface'])
    ->group(function () {
        Route::get('/up', function (Request $request) {
            return response()->json([
                'status' => true,
                'surface' => $request->attributes->get(config('tenancy.surface_request_attribute', 'saas.surface')),
                'scaffold' => 'storefront',
            ]);
        })->name('up');

        Route::middleware(['resolveTenantFromHost', 'ensureTenantResolved', 'ensureTenantActive', 'setTenantContext'])
            ->group(function () {
                Route::get('/bootstrap', StorefrontBootstrapController::class)->name('bootstrap');

                Route::get('/tenant-context', function (Request $request) {
                    $tenant = $request->attributes->get(config('tenancy.tenant_request_attribute', 'saas.tenant'));
                    $tenantDomain = $request->attributes->get(config('tenancy.tenant_domain_attribute', 'saas.tenant_domain'));

                    return response()->json([
                        'status' => true,
                        'tenant' => $tenant?->only(['id', 'name', 'slug', 'status']),
                        'domain' => $tenantDomain?->only(['hostname', 'domain_type', 'is_primary', 'is_fallback']),
                    ]);
                })->name('tenant-context');
            });
    });
