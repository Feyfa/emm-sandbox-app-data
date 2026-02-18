<?php

use Illuminate\Support\Facades\Route;

Route::prefix('user')->name('user.')->group(base_path('routes/api/user.php'));
Route::prefix('campaign')->name('campaign.')->group(base_path('routes/api/campaign.php'));