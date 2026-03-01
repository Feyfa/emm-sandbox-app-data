<?php

use App\Http\Controllers\WhopWebhookController;
use Illuminate\Support\Facades\Route;

// ─── Public: Whop Webhook (no auth) ──────────────────────────────────────────
Route::post('/webhook/whop', [WhopWebhookController::class, 'handle']);

// ─── Protected routes dengan Clerk authentication ─────────────────────────────
Route::middleware('clerk.auth')->group(function () {
    Route::prefix('auth')->name('auth.')->group(base_path('routes/api/auth.php'));
    Route::prefix('user')->name('user.')->group(base_path('routes/api/user.php'));
    Route::prefix('campaign')->name('campaign.')->group(base_path('routes/api/campaign.php'));
    Route::prefix('payment-methods')->name('payment-methods.')->group(base_path('routes/api/payment_method.php'));
    Route::prefix('invoices')->name('invoices.')->group(base_path('routes/api/invoice.php'));
});