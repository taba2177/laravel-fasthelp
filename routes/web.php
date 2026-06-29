<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatbotSetupController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/chatbot/setup', [ChatbotSetupController::class, 'showSetupForm'])->name('chatbot.setup');
Route::post('/chatbot/setup', [ChatbotSetupController::class, 'processSetup']);
Route::get('/chatbot/chat', function () {
    return view('chatbot.chat');
})->name('chatbot.chat');

