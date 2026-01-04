<?php

use App\Http\Controllers\Web\ResponseController;
use Illuminate\Support\Facades\Route;

// Public reminder response page (no auth required)
Route::get('/respond', [ResponseController::class, 'show'])->name('reminder.respond');

// SPA catch-all route - Vue Router handles frontend routing
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api|sanctum|respond).*$');
