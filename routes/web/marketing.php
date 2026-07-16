<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::domain(config('saas.marketing_host'))
    ->middleware(['installed', 'identifySurface'])
    ->group(function () {
        Route::get('/up', function (Request $request) {
            return response()->json([
                'status' => true,
                'surface' => $request->attributes->get(config('tenancy.surface_request_attribute', 'saas.surface')),
                'scaffold' => 'marketing-web',
            ]);
        })->name('marketing.up');
    });
