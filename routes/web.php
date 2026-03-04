<?php

use App\Http\Controllers\TestController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/jidan', function () {
    return response()->json([
        'first_name' => 'Muhammad',
        'last_name' => 'Jidann',
    ]);
});
Route::get('/agies', function () {
    return response()->json([
        'first_name' => 'Agies',
        'last_name' => 'Wahyudi',
    ]);
});

Route::get('/test/whop-one-time', [TestController::class, 'whopOneTime']);
Route::get('/test/whop-transfer-sales', [TestController::class, 'whopTransferSales']);
Route::get('/test/db/read', [TestController::class, 'dbRead']);
Route::match(['get', 'post'], '/test/db/create', [TestController::class, 'dbCreate']);