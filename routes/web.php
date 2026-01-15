<?php

use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Web\ResponseController;
use Illuminate\Support\Facades\Route;

// Public reminder response page (no auth required)
Route::get('/respond', [ResponseController::class, 'show'])->name('reminder.respond');

// Magic link authentication routes
Route::prefix('auth/magic-link')->group(function () {
    // Rate limit email-sending endpoints only
    Route::post('/send', [MagicLinkController::class, 'send'])
        ->middleware('throttle:magic-link')
        ->name('magic-link.send');
    Route::post('/signup', [MagicLinkController::class, 'signup'])
        ->middleware('throttle:magic-link')
        ->name('magic-link.signup');
    // Verify endpoint is protected by single-use tokens, no rate limit needed
    Route::get('/verify/{token}', [MagicLinkController::class, 'verify'])
        ->name('magic-link.verify');
});

// Stripe webhooks (handled by Laravel Cashier)
Route::post('/stripe/webhook', [\Laravel\Cashier\Http\Controllers\WebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');

// SPA catch-all route - Vue Router handles frontend routing
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api|sanctum|respond|stripe|auth).*$');
