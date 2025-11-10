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
// Server-Sent Events stream for real-time cache updates
Route::get('/cache-stream', [SmsController::class, 'cacheStream']);
// Server-side helper routes to test external endpoints (avoids CORS by proxying through the server)
Route::post('/test-poll', [SmsController::class, 'testPoll']);
Route::post('/poll-fetch', [SmsController::class, 'pollFetch']);
Route::post('/test-send', [SmsController::class, 'testSend']);
Route::post('/send-reply', [SmsController::class, 'sendReply']);
Route::post('/templates/save', [SmsController::class, 'saveTemplate']);
Route::get('/templates', [SmsController::class, 'getTemplates']);
// Persist UI settings (endpoints, headers, user id) server-side (cache)
Route::post('/settings/save', [SmsController::class, 'saveSettings']);
Route::get('/settings', [SmsController::class, 'getSettings']);
