<?php

use App\Http\Controllers\MusicController;
use App\Http\Controllers\JobController;
use Illuminate\Support\Facades\Route;

Route::middleware(['hasUser'])->group(function () {
    Route::get('/', [MusicController::class, 'index']);
    Route::post('/scan', [MusicController::class, 'scan']);
    Route::post('/music/{music}/metadata', [MusicController::class, 'searchMetadata']);
    Route::post('/music/{music}/apply-metadata', [MusicController::class, 'applyMetadata']);
    Route::delete('/music/{music}', [MusicController::class, 'destroy']);
    Route::get('/music/{music}/stream', [MusicController::class, 'stream'])->name('music.stream');
    
    // Job monitoring routes
    Route::get('/jobs/stats', [JobController::class, 'stats']);
    Route::delete('/jobs/failed/{id}', [JobController::class, 'deleteFailed']);
});
