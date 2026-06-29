<?php

use App\Http\Controllers\SendController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'throttle:60,1', 'verified'])->group(function () {
    Route::resource('sends', SendController::class)->except(['index', 'edit', 'update']);
});

Route::get('/dashboard', [SendController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__.'/settings.php';
