<?php

use App\Http\Controllers\Saas\AdminSurfaceAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('merchant/auth')
    ->name('merchant.auth.')
    ->middleware(['installed', 'apiKey', 'localization', 'identifySurface', 'ensureMerchantHost'])
    ->group(function () {
        Route::get('/up', function (\Illuminate\Http\Request $request) {
            return response()->json([
                'status' => true,
                'surface' => $request->attributes->get(config('tenancy.surface_request_attribute', 'saas.surface')),
                'scaffold' => 'merchant-auth',
            ]);
        })->name('up');

        Route::post('/register', [AdminSurfaceAuthController::class, 'merchantRegister'])->name('register');
        Route::post('/login', [AdminSurfaceAuthController::class, 'merchantLogin'])->name('login');
        Route::middleware(['auth:sanctum', 'surfaceToken:merchant'])->group(function () {
            Route::get('/me', [AdminSurfaceAuthController::class, 'me'])->defaults('surface', 'merchant')->name('me');
            Route::post('/logout', [AdminSurfaceAuthController::class, 'logout'])->name('logout');
        });
    });
