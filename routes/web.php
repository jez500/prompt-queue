<?php

use App\Http\Controllers\PromptController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::post('prompts', [PromptController::class, 'store'])->name('prompts.store');
});

require __DIR__.'/settings.php';
