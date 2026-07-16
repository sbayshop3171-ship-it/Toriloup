<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::domain(config('saas.owner_host'))
    ->middleware(['installed', 'identifySurface', 'ensurePlatformHost'])
    ->group(function () {
        Route::get('/up', function (Request $request) {
            return response()->json([
                'status' => true,
                'surface' => $request->attributes->get(config('tenancy.surface_request_attribute', 'saas.surface')),
                'scaffold' => 'platform-web',
            ]);
        })->name('platform.web.up');
    });
