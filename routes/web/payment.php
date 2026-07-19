<?php

use App\Http\Controllers\Frontend\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('payment')
    ->name('payment.')
    ->middleware(['installed', 'identifySurface', 'resolveTenantFromHost', 'ensureTenantActive', 'setTenantContext'])
    ->group(function () {
        Route::get('/{paymentGateway}/pay/{order}', [PaymentController::class, 'index'])->name('index');
        Route::post('/{order}/pay', [PaymentController::class, 'payment'])->name('store');
        Route::match(['get', 'post'], '/{paymentGateway}/{order}/success', [PaymentController::class, 'success'])->name('success');
        Route::match(['get', 'post'], '/{paymentGateway}/{order}/fail', [PaymentController::class, 'fail'])->name('fail');
        Route::match(['get', 'post'], '/{paymentGateway}/{order}/cancel', [PaymentController::class, 'cancel'])->name('cancel');
        Route::get('/successful/{order}', [PaymentController::class, 'successful'])->name('successful');
    });
