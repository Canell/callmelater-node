<?php

use App\Http\Controllers\Api\ActionController;
use App\Http\Controllers\Api\ResponseController;
use App\Http\Controllers\Api\TokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public endpoint for reminder responses (token-based auth, rate limited)
Route::post('/v1/respond', [ResponseController::class, 'respond'])
    ->middleware('throttle:reminder-response');

// Authenticated endpoints (Bearer token or SPA cookie)
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Current user
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // API Token Management
    Route::get('/tokens', [TokenController::class, 'index']);
    Route::post('/tokens', [TokenController::class, 'store']);
    Route::delete('/tokens/{id}', [TokenController::class, 'destroy']);

    // Actions API (v1)
    Route::prefix('v1')->group(function () {
        Route::get('/actions', [ActionController::class, 'index']);
        Route::post('/actions', [ActionController::class, 'store'])
            ->middleware('throttle:create-action');
        Route::get('/actions/{id}', [ActionController::class, 'show']);
        // Cancel by idempotency key (must be before /{id} route)
        Route::delete('/actions', [ActionController::class, 'destroyByIdempotencyKey']);
        Route::delete('/actions/{id}', [ActionController::class, 'destroy']);
    });
});
