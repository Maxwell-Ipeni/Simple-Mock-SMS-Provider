<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SmsController;


Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// Fake SMS provider API routes handled by SmsController
Route::post('/send-message', [SmsController::class, 'sendMessage']);
Route::post('/get-message', [SmsController::class, 'getMessage']);
Route::post('/submit', [SmsController::class, 'submit']);
Route::get('/cache-watch', [SmsController::class, 'cacheWatch']);
