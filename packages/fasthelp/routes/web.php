<?php

use Illuminate\Support\Facades\Route;
use Tabadev\FastHelp\Http\Controllers\WidgetController;

Route::prefix(config('fasthelp.routes.prefix', 'fasthelp'))
    ->middleware(config('fasthelp.routes.middleware', ['web']))
    ->name('fasthelp.')
    ->group(function () {
        Route::post('conversation', [WidgetController::class, 'start'])->name('conversation.start');
        Route::get('conversation/{uuid}/messages', [WidgetController::class, 'messages'])->name('conversation.messages');
        Route::post('conversation/{uuid}/messages', [WidgetController::class, 'postMessage'])->name('conversation.post');
        Route::get('status', [WidgetController::class, 'status'])->name('status');
    });
