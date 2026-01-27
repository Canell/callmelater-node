<?php

use App\Http\Controllers\Api\TemplateTriggerController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Web\ResponseController;
use Illuminate\Support\Facades\Route;

// Public reminder response page (no auth required)
Route::get('/respond', [ResponseController::class, 'show'])->name('reminder.respond');
Route::get('/r/{token}', [ResponseController::class, 'showShort'])->name('reminder.respond.short');

// Public template trigger endpoint (token-based auth)
Route::post('/t/{token}', [TemplateTriggerController::class, 'trigger'])
    ->middleware('throttle:template-trigger')
    ->where('token', '[a-zA-Z0-9_]{53}');

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

// Marketing pages - server-rendered Blade for SEO
Route::get('/', fn () => view('pages.home'))->name('home');
Route::get('/pricing', fn () => view('pages.pricing'))->name('pricing');
Route::get('/use-cases', fn () => view('pages.use-cases'))->name('use-cases');
Route::get('/contact', fn () => view('pages.contact'))->name('contact');
Route::get('/terms', fn () => view('pages.terms'))->name('terms');
Route::get('/privacy', fn () => view('pages.privacy'))->name('privacy');
Route::get('/cookies', fn () => view('pages.cookies'))->name('cookies');

// SPA catch-all route - Vue Router handles authenticated routes
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api|sanctum|respond|r/|t/|stripe|auth).*$');
