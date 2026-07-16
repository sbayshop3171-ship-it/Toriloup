<?php

use App\Http\Controllers\Auth\DeactivateController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SignupController;
use App\Http\Controllers\Saas\StorefrontAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('storefront/auth')
    ->name('storefront.auth.')
    ->middleware(['installed', 'apiKey', 'localization', 'identifySurface', 'resolveTenantFromHost', 'ensureTenantResolved', 'ensureTenantActive', 'setTenantContext'])
    ->group(function () {
        Route::get('/up', function (\Illuminate\Http\Request $request) {
            $tenant = $request->attributes->get(config('tenancy.tenant_request_attribute', 'saas.tenant'));

            return response()->json([
                'status' => true,
                'surface' => $request->attributes->get(config('tenancy.surface_request_attribute', 'saas.surface')),
                'scaffold' => 'storefront-auth',
                'tenant' => $tenant?->slug,
            ]);
        })->name('up');

        Route::post('/login', [StorefrontAuthController::class, 'login'])->name('login');

        Route::prefix('forgot-password')->name('forgot-password.')->group(function () {
            Route::post('/', [ForgotPasswordController::class, 'forgotPassword']);
            Route::post('/otp-phone', [ForgotPasswordController::class, 'otpPhone']);
            Route::post('/otp-email', [ForgotPasswordController::class, 'otpEmail']);
            Route::post('/verify-phone', [ForgotPasswordController::class, 'verifyPhone']);
            Route::post('/verify-email', [ForgotPasswordController::class, 'verifyEmail']);
            Route::post('/reset-password', [StorefrontAuthController::class, 'resetPassword']);
        });

        Route::prefix('signup')->name('signup.')->group(function () {
            Route::post('/otp-phone', [SignupController::class, 'otpPhone']);
            Route::post('/otp-email', [SignupController::class, 'otpEmail']);
            Route::post('/verify-phone', [SignupController::class, 'verifyPhone']);
            Route::post('/verify-email', [SignupController::class, 'verifyEmail']);
            Route::post('/register', [StorefrontAuthController::class, 'register']);
            Route::post('/login-verify', [StorefrontAuthController::class, 'loginVerify']);
            Route::post('/register-validation', [SignupController::class, 'validateRegister']);
        });

        Route::middleware(['auth:sanctum', 'surfaceToken:storefront'])->group(function () {
            Route::get('/me', [StorefrontAuthController::class, 'me'])->name('me');
            Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
            Route::post('/delete-account', [DeactivateController::class, 'deleteAccount'])->name('delete-account');
        });
    });
