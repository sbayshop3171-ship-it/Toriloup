<?php

use App\Http\Controllers\Saas\AdminSurfaceAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('platform/auth')
    ->name('platform.auth.')
    ->middleware(['installed', 'apiKey', 'localization', 'identifySurface', 'ensurePlatformHost'])
    ->group(function () {
        Route::get('/up', function (\Illuminate\Http\Request $request) {
            return response()->json([
                'status' => true,
                'surface' => $request->attributes->get(config('tenancy.surface_request_attribute', 'saas.surface')),
                'scaffold' => 'platform-auth',
            ]);
        })->name('up');

        Route::post('/login', [AdminSurfaceAuthController::class, 'platformLogin'])->name('login');
        Route::middleware(['auth:sanctum', 'surfaceToken:platform'])->group(function () {
            Route::get('/me', [AdminSurfaceAuthController::class, 'me'])->defaults('surface', 'platform')->name('me');
            Route::post('/logout', [AdminSurfaceAuthController::class, 'logout'])->name('logout');
        });
    });
