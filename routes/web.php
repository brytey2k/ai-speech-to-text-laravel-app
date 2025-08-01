<?php

declare(strict_types=1);

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// API endpoint for speech segments
Route::post('/speech-segments', [HomeController::class, 'handleSpeechSegment'])
    ->middleware('throttle:5,1')  // Limit to 5 requests per minute
    ->name('speech.segments');
