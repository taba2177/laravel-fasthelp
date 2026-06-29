<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ChatbotController;


Route::post('/chat', [ChatbotController::class, 'chat']);
Route::post('/rive', [ChatbotController::class, 'rive']);
Route::post('/feedback/{logId}', [ChatbotController::class, 'provideFeedback']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
