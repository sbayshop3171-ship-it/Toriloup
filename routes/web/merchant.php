<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::domain(config('saas.merchant_host'))
    ->middleware(['installed', 'identifySurface', 'ensureMerchantHost'])
    ->group(function () {
        Route::get('/up', function (Request $request) {
            return response()->json([
                'status' => true,
                'surface' => $request->attributes->get(config('tenancy.surface_request_attribute', 'saas.surface')),
                'scaffold' => 'merchant-web',
            ]);
        })->name('merchant.web.up');
    });
