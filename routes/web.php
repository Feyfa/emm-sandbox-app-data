<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/jidan', function () {
    return response()->json([
        'first_name' => 'Muhammad',
        'last_name' => 'Jidan',
        'age' => 21,
    ]);
});
