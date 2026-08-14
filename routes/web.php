<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectOrderController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\PromptOrderController;
use App\Http\Controllers\PromptPriorityController;
use App\Http\Controllers\PromptStatusController;
use App\Http\Controllers\WebManifestController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', function (): RedirectResponse {
    if (auth()->check()) {
        return redirect()->route('prompts.index');
    }

    return redirect()->route('login');
})->name('home');

Route::get('manifest.webmanifest', WebManifestController::class)->name('manifest');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', 'prompts')->name('dashboard');

    Route::get('prompts', [PromptController::class, 'index'])->name('prompts.index');
    Route::post('prompts', [PromptController::class, 'store'])->name('prompts.store');
    Route::patch('prompts/reorder', PromptOrderController::class)->name('prompts.reorder');
    Route::patch('prompts/{prompt}', [PromptController::class, 'update'])->name('prompts.update');
    Route::patch('prompts/{prompt}/priority', PromptPriorityController::class)->name('prompts.priority');
    Route::patch('prompts/{prompt}/status', PromptStatusController::class)->name('prompts.status');
    Route::delete('prompts/{prompt}', [PromptController::class, 'destroy'])->name('prompts.destroy');

    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    /* Declared before projects/{project} or the wildcard swallows "reorder". */
    Route::patch('projects/reorder', ProjectOrderController::class)->name('projects.reorder');
    Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
});

require __DIR__.'/settings.php';
