<?php

use App\Http\Controllers\Saas\PlatformDashboardController;
use App\Http\Controllers\Saas\PlatformAuditController;
use App\Http\Controllers\Saas\PlatformCustomerController;
use App\Http\Controllers\Saas\PlatformDomainController;
use App\Http\Controllers\Saas\PlatformPlanController;
use App\Http\Controllers\Saas\PlatformProviderController;
use App\Http\Controllers\Saas\PlatformSubscriptionController;
use App\Http\Controllers\Saas\PlatformTenantController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('platform')
    ->name('platform.')
    ->middleware(['installed', 'apiKey', 'localization', 'identifySurface', 'ensurePlatformHost'])
    ->group(function () {
        Route::get('/up', function (Request $request) {
            return response()->json([
                'status' => true,
                'surface' => $request->attributes->get(config('tenancy.surface_request_attribute', 'saas.surface')),
                'scaffold' => 'platform',
            ]);
        })->name('up');

        Route::middleware(['auth:sanctum', 'surfaceToken:platform'])->group(function () {
            Route::get('/overview', PlatformDashboardController::class)->name('overview');

            Route::prefix('tenants')->name('tenants.')->group(function () {
                Route::get('/', [PlatformTenantController::class, 'index'])->name('index');
                Route::get('/{tenantId}', [PlatformTenantController::class, 'show'])->whereNumber('tenantId')->name('show');
                Route::match(['put', 'patch', 'post'], '/{tenantId}', [PlatformTenantController::class, 'update'])->whereNumber('tenantId')->name('update');
                Route::post('/{tenantId}/approve', [PlatformTenantController::class, 'approve'])->whereNumber('tenantId')->name('approve');
                Route::post('/{tenantId}/suspend', [PlatformTenantController::class, 'suspend'])->whereNumber('tenantId')->name('suspend');
                Route::post('/{tenantId}/reactivate', [PlatformTenantController::class, 'reactivate'])->whereNumber('tenantId')->name('reactivate');
                Route::delete('/{tenantId}', [PlatformTenantController::class, 'destroy'])->whereNumber('tenantId')->name('destroy');
                Route::post('/{tenantId}/domains', [PlatformDomainController::class, 'storeForTenant'])->whereNumber('tenantId')->name('domains.store');
                Route::post('/{tenantId}/subscription', [PlatformSubscriptionController::class, 'assignToTenant'])->whereNumber('tenantId')->name('subscription.assign');
            });

            Route::prefix('customers')->name('customers.')->group(function () {
                Route::get('/', [PlatformCustomerController::class, 'index'])->name('index');
                Route::get('/{customerId}', [PlatformCustomerController::class, 'show'])->name('show');
            });

            Route::prefix('domains')->name('domains.')->group(function () {
                Route::get('/', [PlatformDomainController::class, 'index'])->name('index');
                Route::post('/{domainId}/verify', [PlatformDomainController::class, 'verify'])->whereNumber('domainId')->name('verify');
                Route::post('/{domainId}/primary', [PlatformDomainController::class, 'setPrimary'])->whereNumber('domainId')->name('primary');
            });

            Route::prefix('plans')->name('plans.')->group(function () {
                Route::get('/', [PlatformPlanController::class, 'index'])->name('index');
                Route::get('/{planCode}', [PlatformPlanController::class, 'show'])->name('show');
                Route::match(['put', 'patch', 'post'], '/{planCode}', [PlatformPlanController::class, 'upsert'])->name('upsert');
            });

            Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
                Route::get('/', [PlatformSubscriptionController::class, 'index'])->name('index');
                Route::get('/{subscriptionId}', [PlatformSubscriptionController::class, 'show'])->whereNumber('subscriptionId')->name('show');
                Route::post('/{subscriptionId}/invoices/{invoiceId}/mark-paid', [PlatformSubscriptionController::class, 'markInvoicePaid'])
                    ->whereNumber('subscriptionId')
                    ->whereNumber('invoiceId')
                    ->name('invoices.mark-paid');
            });

            Route::prefix('providers')->name('providers.')->group(function () {
                Route::get('/', [PlatformProviderController::class, 'index'])->name('index');
                Route::match(['put', 'patch', 'post'], '/{providerCode}', [PlatformProviderController::class, 'upsert'])->name('upsert');
            });

            Route::get('/audit-logs', [PlatformAuditController::class, 'index'])->name('audit-logs.index');
        });
    });
