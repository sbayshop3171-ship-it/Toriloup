<?php

use App\Http\Controllers\Saas\AdminSurfaceAuthController;
use App\Http\Controllers\Saas\AdminSurfacePasswordController;
use App\Http\Controllers\Saas\MerchantSupportSessionController;
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
        Route::post('/support-sessions/consume', [MerchantSupportSessionController::class, 'consume'])->name('support-sessions.consume');
        Route::prefix('forgot-password')->name('forgot-password.')->group(function () {
            Route::post('/', [AdminSurfacePasswordController::class, 'forgotPassword'])->defaults('surface', 'merchant')->name('request');
            Route::post('/otp-phone', [AdminSurfacePasswordController::class, 'otpPhone'])->defaults('surface', 'merchant')->name('otp-phone');
            Route::post('/otp-email', [AdminSurfacePasswordController::class, 'otpEmail'])->defaults('surface', 'merchant')->name('otp-email');
            Route::post('/verify-phone', [AdminSurfacePasswordController::class, 'verifyPhone'])->defaults('surface', 'merchant')->name('verify-phone');
            Route::post('/verify-email', [AdminSurfacePasswordController::class, 'verifyEmail'])->defaults('surface', 'merchant')->name('verify-email');
            Route::post('/reset-password', [AdminSurfacePasswordController::class, 'resetPassword'])->defaults('surface', 'merchant')->name('reset-password');
        });
        Route::middleware(['auth:sanctum', 'surfaceToken:merchant'])->group(function () {
            Route::get('/me', [AdminSurfaceAuthController::class, 'me'])->defaults('surface', 'merchant')->name('me');
            Route::post('/logout', [AdminSurfaceAuthController::class, 'logout'])->name('logout');
            Route::post('/support-sessions/{sessionId}/end', [MerchantSupportSessionController::class, 'end'])
                ->whereNumber('sessionId')
                ->name('support-sessions.end');
        });
    });
