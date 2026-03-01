<?php

use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

// ─── Invoices ────────────────────────────────────────────────────────────────
Route::controller(InvoiceController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/{id}', 'show')->name('show');
    Route::post('/charge-credits', 'chargeCredits')->name('charge-credits');
    Route::post('/subscribe', 'subscribe')->name('subscribe');
});
