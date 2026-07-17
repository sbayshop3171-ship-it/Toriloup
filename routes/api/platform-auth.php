<?php

use App\Http\Controllers\Saas\AdminSurfaceAuthController;
use App\Http\Controllers\Saas\AdminSurfacePasswordController;
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
        Route::prefix('forgot-password')->name('forgot-password.')->group(function () {
            Route::post('/', [AdminSurfacePasswordController::class, 'forgotPassword'])->defaults('surface', 'platform')->name('request');
            Route::post('/otp-phone', [AdminSurfacePasswordController::class, 'otpPhone'])->defaults('surface', 'platform')->name('otp-phone');
            Route::post('/otp-email', [AdminSurfacePasswordController::class, 'otpEmail'])->defaults('surface', 'platform')->name('otp-email');
            Route::post('/verify-phone', [AdminSurfacePasswordController::class, 'verifyPhone'])->defaults('surface', 'platform')->name('verify-phone');
            Route::post('/verify-email', [AdminSurfacePasswordController::class, 'verifyEmail'])->defaults('surface', 'platform')->name('verify-email');
            Route::post('/reset-password', [AdminSurfacePasswordController::class, 'resetPassword'])->defaults('surface', 'platform')->name('reset-password');
        });
        Route::middleware(['auth:sanctum', 'surfaceToken:platform'])->group(function () {
            Route::get('/me', [AdminSurfaceAuthController::class, 'me'])->defaults('surface', 'platform')->name('me');
            Route::post('/logout', [AdminSurfaceAuthController::class, 'logout'])->name('logout');
        });
    });
