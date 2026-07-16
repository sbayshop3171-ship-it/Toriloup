<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('system')
    ->name('system.')
    ->middleware(['installed', 'identifySurface'])
    ->group(function () {
        Route::get('/up', function (Request $request) {
            return response()->json([
                'status' => true,
                'surface' => $request->attributes->get(config('tenancy.surface_request_attribute', 'saas.surface')),
                'scaffold' => 'system',
            ]);
        })->name('up');
    });
