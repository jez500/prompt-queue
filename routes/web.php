<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\PromptOrderController;
use App\Http\Controllers\PromptStatusController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('prompts', [PromptController::class, 'index'])->name('prompts.index');
    Route::post('prompts', [PromptController::class, 'store'])->name('prompts.store');
    Route::patch('prompts/reorder', PromptOrderController::class)->name('prompts.reorder');
    Route::patch('prompts/{prompt}', [PromptController::class, 'update'])->name('prompts.update');
    Route::patch('prompts/{prompt}/status', PromptStatusController::class)->name('prompts.status');
    Route::delete('prompts/{prompt}', [PromptController::class, 'destroy'])->name('prompts.destroy');

    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
});

require __DIR__.'/settings.php';
