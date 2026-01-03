<?php

use Illuminate\Support\Facades\Route;

// SPA catch-all route - Vue Router handles frontend routing
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api|sanctum).*$');
