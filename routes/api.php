<?php

use App\Http\Controllers\Admin\StatusController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\ActionController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\HeartbeatController;
use App\Http\Controllers\Api\ConsentController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DomainController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\ResponseController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\PublicStatusController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public status endpoint (heavily cached, no auth required)
Route::get('/public/status', [PublicStatusController::class, 'index'])
    ->middleware('throttle:status');

// Public server info (for webhook documentation)
Route::get('/public/server-info', function () {
    return response()->json([
        'outbound_ip' => config('services.callmelater.outbound_ip'),
        'webhook_headers' => [
            'X-CallMeLater-Signature',
            'X-CallMeLater-Action-Id',
            'X-CallMeLater-Timestamp',
        ],
        'user_agent' => 'CallMeLater/1.0',
    ])->header('Cache-Control', 'public, max-age=3600');
})->middleware('throttle:status');

// Public endpoint for reminder responses (token-based auth, rate limited)
Route::post('/v1/respond', [ResponseController::class, 'respond'])
    ->middleware('throttle:reminder-response');

// Public contact form (rate limited)
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact');

// Internal heartbeat endpoint (receives self-pings from health monitor)
Route::post('/internal/heartbeat', [HeartbeatController::class, 'ping'])
    ->name('api.health.heartbeat')
    ->middleware('throttle:heartbeat');

// Public consent endpoints (token-based, no login required)
Route::prefix('v1/consent')->middleware('throttle:consent')->group(function () {
    Route::get('/accept/{token}', [ConsentController::class, 'accept']);
    Route::get('/decline/{token}', [ConsentController::class, 'decline']);
    Route::get('/unsubscribe/{token}', [ConsentController::class, 'unsubscribe']);
});

// Public invitation endpoint (view invitation details)
Route::get('/invitations/{token}', [InvitationController::class, 'show'])
    ->middleware('throttle:api');

// Authenticated endpoints (Bearer token or SPA cookie)
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Current user
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // User settings
    Route::put('/user/profile', [UserController::class, 'updateProfile']);
    Route::put('/user/password', [UserController::class, 'updatePassword']);
    Route::get('/user/webhook-secret', [UserController::class, 'getWebhookSecret']);
    Route::post('/user/webhook-secret', [UserController::class, 'regenerateWebhookSecret']);
    Route::put('/user/notifications', [UserController::class, 'updateNotifications']);
    Route::delete('/user', [UserController::class, 'destroy']);

    // Admin notification preferences (for admins only)
    Route::get('/user/admin-notifications', [UserController::class, 'getAdminNotifications']);
    Route::put('/user/admin-notifications', [UserController::class, 'updateAdminNotifications']);

    // API Token Management
    Route::get('/tokens', [TokenController::class, 'index']);
    Route::post('/tokens', [TokenController::class, 'store']);
    Route::delete('/tokens/{id}', [TokenController::class, 'destroy']);

    // Team Invitations
    Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept']);

    // Actions API (v1)
    Route::prefix('v1')->group(function () {
        Route::get('/actions', [ActionController::class, 'index']);
        Route::post('/actions', [ActionController::class, 'store'])
            ->middleware('throttle:create-action');
        Route::post('/actions/test', [ActionController::class, 'test'])
            ->middleware(['throttle:test-action-url', 'throttle:test-action-user']);
        Route::get('/actions/{id}', [ActionController::class, 'show']);
        // Cancel by idempotency key (must be before /{id} route)
        Route::delete('/actions', [ActionController::class, 'destroyByIdempotencyKey']);
        Route::delete('/actions/{id}', [ActionController::class, 'destroy']);

        // Domain Verification
        Route::get('/domains', [DomainController::class, 'index']);
        Route::get('/domains/{domain}', [DomainController::class, 'show']);
        Route::post('/domains/{domain}/verify', [DomainController::class, 'verify']);
        Route::delete('/domains/{domain}', [DomainController::class, 'destroy']);
    });

    // Subscription Management
    Route::prefix('subscription')->group(function () {
        Route::get('/status', [SubscriptionController::class, 'status']);
        Route::post('/checkout', [SubscriptionController::class, 'checkout']);
        Route::post('/portal', [SubscriptionController::class, 'portal']);
        Route::post('/cancel', [SubscriptionController::class, 'cancel']);
        Route::post('/resume', [SubscriptionController::class, 'resume']);
    });

    // Account Management
    Route::prefix('account')->group(function () {
        Route::get('/', [AccountController::class, 'show']);
        Route::put('/', [AccountController::class, 'update']);
        Route::post('/members', [AccountController::class, 'addMember']);
        Route::delete('/members/{userId}', [AccountController::class, 'removeMember']);
    });

    // Team Management (Business plan)
    Route::prefix('teams')->group(function () {
        Route::get('/', [TeamController::class, 'index']);
        Route::post('/', [TeamController::class, 'store']);
        Route::get('/{team}', [TeamController::class, 'show']);
        Route::put('/{team}', [TeamController::class, 'update']);
        Route::delete('/{team}', [TeamController::class, 'destroy']);
        Route::post('/{team}/members', [TeamController::class, 'addMember']);
        Route::delete('/{team}/members/{userId}', [TeamController::class, 'removeMember']);
        Route::get('/{team}/invitations', [TeamController::class, 'invitations']);
        Route::delete('/{team}/invitations/{invitation}', [TeamController::class, 'cancelInvitation']);
    });

    // Admin Dashboard (requires admin role)
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/stats/overview', [AdminController::class, 'overview']);
        Route::get('/stats/trends', [AdminController::class, 'trends']);
        Route::get('/health', [AdminController::class, 'health']);
        Route::get('/queue', [AdminController::class, 'queue']);
        Route::get('/heartbeat', [HeartbeatController::class, 'status']);
        Route::get('/users', [AdminController::class, 'users']);

        // Account Plan Management
        Route::post('/accounts/{account}/manual-plan', [AdminController::class, 'setManualPlan']);
        Route::get('/accounts/{account}/plan-overrides', [AdminController::class, 'getPlanOverrides']);

        // Status Page Management
        Route::get('/status/components', [StatusController::class, 'components']);
        Route::patch('/status/components/{component}', [StatusController::class, 'updateComponent']);
        Route::get('/status/components/{component}/history', [StatusController::class, 'componentHistory']);
        Route::get('/status/incidents', [StatusController::class, 'incidents']);
        Route::post('/status/incidents', [StatusController::class, 'createIncident']);
        Route::patch('/status/incidents/{incident}', [StatusController::class, 'updateIncident']);
    });
});
