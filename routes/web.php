<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// API endpoint for speech segments
Route::post('/api/speech-segments', [HomeController::class, 'handleSpeechSegment'])->name('speech.segments');
