<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Saas\MerchantOrderController;
use App\Http\Controllers\Saas\MerchantCatalogController;
use App\Http\Controllers\Saas\MerchantProductController;
use App\Http\Controllers\Saas\MerchantCustomerController;
use App\Http\Controllers\Saas\MerchantDashboardController;
use App\Http\Controllers\Saas\MerchantSettingsController;
use App\Http\Controllers\Saas\MerchantStockController;
use App\Http\Controllers\Saas\MerchantSupplierController;
use App\Http\Controllers\Saas\MerchantAttributeController;
use App\Http\Controllers\Saas\MerchantBillingController;
use App\Http\Controllers\Saas\MerchantVariationController;
use App\Http\Controllers\Saas\MerchantDamageController;
use App\Http\Controllers\Saas\MerchantDomainController;
use App\Http\Controllers\Saas\MerchantPosController;
use App\Http\Controllers\Saas\MerchantPurchaseController;
use App\Http\Controllers\Saas\MerchantReturnOrderController;
use App\Http\Controllers\Saas\MerchantReturnAndRefundController;

Route::prefix('merchant')
    ->name('merchant.')
    ->middleware(['installed', 'apiKey', 'localization', 'identifySurface', 'ensureMerchantHost'])
    ->group(function () {
        Route::get('/up', function (Request $request) {
            return response()->json([
                'status' => true,
                'surface' => $request->attributes->get(config('tenancy.surface_request_attribute', 'saas.surface')),
                'scaffold' => 'merchant',
            ]);
        })->name('up');

        Route::middleware(['auth:sanctum', 'surfaceToken:merchant', 'resolveTenantFromMerchantMembership', 'ensureTenantResolved', 'ensureTenantActive', 'setTenantContext'])
            ->group(function () {
                Route::get('/context', function (Request $request) {
                    $tenant = $request->attributes->get(config('tenancy.tenant_request_attribute', 'saas.tenant'));
                    $tenantDomain = $request->attributes->get(config('tenancy.tenant_domain_attribute', 'saas.tenant_domain'));

                    return response()->json([
                        'status' => true,
                        'surface' => $request->attributes->get(config('tenancy.surface_request_attribute', 'saas.surface')),
                        'tenant' => $tenant?->only(['id', 'uuid', 'name', 'slug', 'status', 'plan_code', 'onboarding_status']),
                        'domain' => $tenantDomain?->only(['hostname', 'domain_type', 'is_primary', 'is_fallback']),
                    ]);
                })->name('context');

                Route::get('/dashboard/setup', [MerchantDashboardController::class, 'setup']);

                Route::prefix('catalog')->group(function () {
                    Route::get('/categories', [MerchantCatalogController::class, 'categories']);
                    Route::post('/categories', [MerchantCatalogController::class, 'storeCategory']);
                    Route::get('/categories/{productCategory}', [MerchantCatalogController::class, 'showCategory'])->whereNumber('productCategory');
                    Route::match(['put', 'patch', 'post'], '/categories/{productCategory}', [MerchantCatalogController::class, 'updateCategory'])->whereNumber('productCategory');
                    Route::delete('/categories/{productCategory}', [MerchantCatalogController::class, 'destroyCategory'])->whereNumber('productCategory');
                    Route::get('/brands', [MerchantCatalogController::class, 'brands']);
                    Route::post('/brands', [MerchantCatalogController::class, 'storeBrand']);
                    Route::get('/brands/{productBrand}', [MerchantCatalogController::class, 'showBrand'])->whereNumber('productBrand');
                    Route::match(['put', 'patch', 'post'], '/brands/{productBrand}', [MerchantCatalogController::class, 'updateBrand'])->whereNumber('productBrand');
                    Route::delete('/brands/{productBrand}', [MerchantCatalogController::class, 'destroyBrand'])->whereNumber('productBrand');
                    Route::get('/units', [MerchantCatalogController::class, 'units']);
                    Route::post('/units', [MerchantCatalogController::class, 'storeUnit']);
                    Route::get('/units/{unit}', [MerchantCatalogController::class, 'showUnit'])->whereNumber('unit');
                    Route::match(['put', 'patch', 'post'], '/units/{unit}', [MerchantCatalogController::class, 'updateUnit'])->whereNumber('unit');
                    Route::delete('/units/{unit}', [MerchantCatalogController::class, 'destroyUnit'])->whereNumber('unit');
                    Route::get('/attributes', [MerchantAttributeController::class, 'index']);
                    Route::post('/attributes', [MerchantAttributeController::class, 'store']);
                    Route::get('/attributes/{attributeId}', [MerchantAttributeController::class, 'show'])->whereNumber('attributeId');
                    Route::match(['put', 'patch', 'post'], '/attributes/{attributeId}', [MerchantAttributeController::class, 'update'])->whereNumber('attributeId');
                    Route::delete('/attributes/{attributeId}', [MerchantAttributeController::class, 'destroy'])->whereNumber('attributeId');
                    Route::get('/attributes/{attributeId}/options', [MerchantAttributeController::class, 'options'])->whereNumber('attributeId');
                    Route::post('/attributes/{attributeId}/options', [MerchantAttributeController::class, 'storeOption'])->whereNumber('attributeId');
                    Route::get('/attributes/{attributeId}/options/{optionId}', [MerchantAttributeController::class, 'showOption'])->whereNumber('attributeId')->whereNumber('optionId');
                    Route::match(['put', 'patch', 'post'], '/attributes/{attributeId}/options/{optionId}', [MerchantAttributeController::class, 'updateOption'])->whereNumber('attributeId')->whereNumber('optionId');
                    Route::delete('/attributes/{attributeId}/options/{optionId}', [MerchantAttributeController::class, 'destroyOption'])->whereNumber('attributeId')->whereNumber('optionId');
                });

                Route::prefix('suppliers')->group(function () {
                    Route::get('/', [MerchantSupplierController::class, 'index']);
                    Route::post('/', [MerchantSupplierController::class, 'store']);
                    Route::get('/{supplierId}', [MerchantSupplierController::class, 'show'])->whereNumber('supplierId');
                    Route::match(['put', 'patch', 'post'], '/{supplierId}', [MerchantSupplierController::class, 'update'])->whereNumber('supplierId');
                    Route::delete('/{supplierId}', [MerchantSupplierController::class, 'destroy'])->whereNumber('supplierId');
                });

                Route::prefix('purchases')->group(function () {
                    Route::get('/', [MerchantPurchaseController::class, 'index']);
                    Route::post('/', [MerchantPurchaseController::class, 'store']);
                    Route::get('/{purchaseId}', [MerchantPurchaseController::class, 'show'])->whereNumber('purchaseId');
                    Route::get('/{purchaseId}/edit', [MerchantPurchaseController::class, 'edit'])->whereNumber('purchaseId');
                    Route::match(['put', 'patch', 'post'], '/{purchaseId}', [MerchantPurchaseController::class, 'update'])->whereNumber('purchaseId');
                    Route::delete('/{purchaseId}', [MerchantPurchaseController::class, 'destroy'])->whereNumber('purchaseId');
                    Route::get('/{purchaseId}/payments', [MerchantPurchaseController::class, 'paymentHistory'])->whereNumber('purchaseId');
                    Route::post('/{purchaseId}/payments', [MerchantPurchaseController::class, 'payment'])->whereNumber('purchaseId');
                    Route::delete('/{purchaseId}/payments/{paymentId}', [MerchantPurchaseController::class, 'paymentDestroy'])->whereNumber('purchaseId')->whereNumber('paymentId');
                });

                Route::prefix('damages')->group(function () {
                    Route::get('/', [MerchantDamageController::class, 'index']);
                    Route::post('/', [MerchantDamageController::class, 'store']);
                    Route::get('/{damageId}', [MerchantDamageController::class, 'show'])->whereNumber('damageId');
                    Route::get('/{damageId}/edit', [MerchantDamageController::class, 'edit'])->whereNumber('damageId');
                    Route::match(['put', 'patch', 'post'], '/{damageId}', [MerchantDamageController::class, 'update'])->whereNumber('damageId');
                    Route::delete('/{damageId}', [MerchantDamageController::class, 'destroy'])->whereNumber('damageId');
                });

                Route::prefix('products')->group(function () {
                    Route::get('/', [MerchantProductController::class, 'index']);
                    Route::post('/', [MerchantProductController::class, 'store']);
                    Route::get('/generate-sku', [MerchantProductController::class, 'generateSku']);
                    Route::get('/{productId}', [MerchantProductController::class, 'show'])->whereNumber('productId');
                    Route::match(['put', 'patch', 'post'], '/{productId}', [MerchantProductController::class, 'update'])->whereNumber('productId');
                    Route::delete('/{productId}', [MerchantProductController::class, 'destroy'])->whereNumber('productId');
                    Route::post('/{productId}/upload-image', [MerchantProductController::class, 'uploadImage'])->whereNumber('productId');
                    Route::post('/{productId}/shipping-and-return', [MerchantProductController::class, 'shippingAndReturn'])->whereNumber('productId');
                    Route::post('/{productId}/offer', [MerchantProductController::class, 'offer'])->whereNumber('productId');
                    Route::post('/{productId}/offer/clear', [MerchantProductController::class, 'clearOffer'])->whereNumber('productId');

                    Route::prefix('/{productId}/variations')->whereNumber('productId')->group(function () {
                        Route::get('/', [MerchantVariationController::class, 'index']);
                        Route::post('/', [MerchantVariationController::class, 'store']);
                        Route::get('/tree', [MerchantVariationController::class, 'tree']);
                        Route::get('/single-tree', [MerchantVariationController::class, 'singleTree']);
                        Route::get('/tree-with-selected', [MerchantVariationController::class, 'treeWithSelected']);
                        Route::get('/initial', [MerchantVariationController::class, 'initialVariation']);
                        Route::get('/{variationId}', [MerchantVariationController::class, 'show'])->whereNumber('variationId');
                        Route::match(['put', 'patch', 'post'], '/{variationId}', [MerchantVariationController::class, 'update'])->whereNumber('variationId');
                        Route::delete('/{variationId}', [MerchantVariationController::class, 'destroy'])->whereNumber('variationId');
                    });
                });

                Route::get('/variation-children/{variationId}', [MerchantVariationController::class, 'childrenVariation'])->whereNumber('variationId');
                Route::get('/variation-ancestors/{variationId}', [MerchantVariationController::class, 'ancestorsToString'])->whereNumber('variationId');
                Route::get('/variation-ancestor-ids/{variationId}', [MerchantVariationController::class, 'ancestorsAndSelfId'])->whereNumber('variationId');

                Route::prefix('orders')->group(function () {
                    Route::get('/', [MerchantOrderController::class, 'index']);
                    Route::get('/{orderId}', [MerchantOrderController::class, 'show'])->whereNumber('orderId');
                    Route::post('/{orderId}/status', [MerchantOrderController::class, 'changeStatus'])->whereNumber('orderId');
                    Route::post('/{orderId}/payment-status', [MerchantOrderController::class, 'changePaymentStatus'])->whereNumber('orderId');
                });

                Route::prefix('customers')->group(function () {
                    Route::get('/', [MerchantCustomerController::class, 'index']);
                    Route::get('/{customerId}', [MerchantCustomerController::class, 'show'])->whereNumber('customerId');
                    Route::get('/{customerId}/orders', [MerchantCustomerController::class, 'orders'])->whereNumber('customerId');
                });

                Route::prefix('domains')->group(function () {
                    Route::get('/', [MerchantDomainController::class, 'index']);
                    Route::post('/', [MerchantDomainController::class, 'store'])->middleware('tenantFeature:custom_domain');
                    Route::post('/{domainId}/primary', [MerchantDomainController::class, 'setPrimary'])->whereNumber('domainId')->middleware('tenantFeature:custom_domain');
                });

                Route::prefix('settings')->group(function () {
                    Route::get('/company', [MerchantSettingsController::class, 'company']);
                    Route::match(['put', 'patch', 'post'], '/company', [MerchantSettingsController::class, 'updateCompany']);
                    Route::get('/shipping', [MerchantSettingsController::class, 'shipping']);
                    Route::match(['put', 'patch'], '/shipping', [MerchantSettingsController::class, 'updateShipping']);
                    Route::get('/payment-methods', [MerchantSettingsController::class, 'paymentMethods']);
                    Route::match(['put', 'patch'], '/payment-methods', [MerchantSettingsController::class, 'updatePaymentMethods']);
                });

                Route::prefix('billing')->group(function () {
                    Route::get('/summary', [MerchantBillingController::class, 'summary']);
                    Route::get('/invoices', [MerchantBillingController::class, 'invoices']);
                    Route::get('/plans', [MerchantBillingController::class, 'plans']);
                    Route::post('/checkout', [MerchantBillingController::class, 'checkout']);
                });

                Route::get('/stock', [MerchantStockController::class, 'index'])->middleware('tenantFeature:advanced_stock');

                Route::prefix('pos')->middleware('tenantFeature:pos')->group(function () {
                    Route::post('/customers', [MerchantPosController::class, 'storeCustomer']);
                    Route::post('/orders', [MerchantPosController::class, 'store']);
                    Route::get('/orders', [MerchantPosController::class, 'orders']);
                    Route::get('/orders/{orderId}', [MerchantPosController::class, 'showOrder'])->whereNumber('orderId');
                    Route::delete('/orders/{orderId}', [MerchantPosController::class, 'destroyOrder'])->whereNumber('orderId');
                    Route::post('/orders/{orderId}/status', [MerchantPosController::class, 'changeOrderStatus'])->whereNumber('orderId');
                    Route::post('/orders/{orderId}/payment-status', [MerchantPosController::class, 'changePaymentStatus'])->whereNumber('orderId');
                });

                Route::prefix('return-orders')->middleware('tenantFeature:returns')->group(function () {
                    Route::get('/', [MerchantReturnOrderController::class, 'index']);
                    Route::post('/', [MerchantReturnOrderController::class, 'store']);
                    Route::get('/{returnOrderId}', [MerchantReturnOrderController::class, 'show'])->whereNumber('returnOrderId');
                    Route::get('/{returnOrderId}/edit', [MerchantReturnOrderController::class, 'edit'])->whereNumber('returnOrderId');
                    Route::match(['put', 'patch', 'post'], '/{returnOrderId}', [MerchantReturnOrderController::class, 'update'])->whereNumber('returnOrderId');
                    Route::delete('/{returnOrderId}', [MerchantReturnOrderController::class, 'destroy'])->whereNumber('returnOrderId');
                });

                Route::prefix('returns')->middleware('tenantFeature:returns')->group(function () {
                    Route::get('/', [MerchantReturnAndRefundController::class, 'index']);
                    Route::get('/{returnAndRefundId}', [MerchantReturnAndRefundController::class, 'show'])->whereNumber('returnAndRefundId');
                    Route::post('/{returnAndRefundId}/status', [MerchantReturnAndRefundController::class, 'changeStatus'])->whereNumber('returnAndRefundId');
                });
            });
    });
