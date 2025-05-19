<?php

use App\Http\Controllers\MusicController;
use Illuminate\Support\Facades\Route;

Route::middleware(['hasUser'])->group(function () {
    Route::get('/', [MusicController::class, 'index']);
    Route::post('/scan', [MusicController::class, 'scan']);
});
