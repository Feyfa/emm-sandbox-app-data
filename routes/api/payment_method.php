<?php

use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

// ─── Payment Methods ──────────────────────────────────────────────────────────
Route::controller(PaymentMethodController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/setup-intent', 'setupIntent')->name('setup-intent');
    Route::post('/subscription-checkout', 'subscriptionCheckout')->name('subscription-checkout');
    Route::post('/', 'store')->name('store');
    Route::put('/{id}/default', 'setDefault')->name('set-default');
    Route::delete('/{id}', 'destroy')->name('destroy');
});
