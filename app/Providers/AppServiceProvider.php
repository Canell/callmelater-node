<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiting for the application.
     */
    private function configureRateLimiting(): void
    {
        // General API rate limit
        RateLimiter::for('api', function (Request $request) {
            $limit = config('callmelater.rate_limits.api', 100);

            return $request->user()
                ? Limit::perMinute($limit)->by($request->user()->id)
                : Limit::perMinute(20)->by($request->ip());
        });

        // Action creation rate limit
        RateLimiter::for('create-action', function (Request $request) {
            $limit = config('callmelater.rate_limits.create_actions', 100);

            return $request->user()
                ? Limit::perHour($limit)->by($request->user()->id)
                : Limit::perHour(10)->by($request->ip());
        });

        // Reminder response rate limit (public endpoint)
        RateLimiter::for('reminder-response', function (Request $request) {
            $limit = config('callmelater.rate_limits.responses', 10);
            $token = $request->input('token', $request->ip());

            return Limit::perMinute($limit)->by($token);
        });

        // Consent endpoint rate limit
        RateLimiter::for('consent', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        // Contact form rate limit (prevent spam)
        RateLimiter::for('contact', function (Request $request) {
            return [
                Limit::perMinute(3)->by($request->ip()),
                Limit::perHour(10)->by($request->ip()),
            ];
        });

        // Public status page rate limit (generous, cached anyway)
        RateLimiter::for('status', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Test webhook rate limit - per URL (3 per minute)
        RateLimiter::for('test-action-url', function (Request $request) {
            $url = $request->input('url', '');
            $userId = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(3)
                ->by($userId . '|' . md5($url))
                ->response(function (Request $request, array $headers) {
                    $retryAfter = $headers['Retry-After'] ?? 60;

                    return response()->json([
                        'success' => false,
                        'status_code' => null,
                        'duration_ms' => 0,
                        'error' => 'rate_limit_url',
                        'message' => "You've tested this URL too many times. Please wait before testing again.",
                        'retry_after' => (int) $retryAfter,
                    ], 429);
                });
        });

        // Test webhook rate limit - per user (20 per hour)
        RateLimiter::for('test-action-user', function (Request $request) {
            $userId = $request->user()?->id ?? $request->ip();

            return Limit::perHour(20)
                ->by($userId)
                ->response(function (Request $request, array $headers) {
                    $retryAfter = $headers['Retry-After'] ?? 3600;

                    return response()->json([
                        'success' => false,
                        'status_code' => null,
                        'duration_ms' => 0,
                        'error' => 'rate_limit_user',
                        'message' => "You've reached your hourly test limit. Please wait before testing again.",
                        'retry_after' => (int) $retryAfter,
                    ], 429);
                });
        });

        // Magic link rate limit - prevent abuse
        RateLimiter::for('magic-link', function (Request $request) {
            $email = strtolower(trim($request->input('email', '')));

            return [
                // Per IP: 5 requests per minute
                Limit::perMinute(5)->by($request->ip()),
                // Per email: 3 requests per 10 minutes
                Limit::perMinutes(10, 3)->by('email:' . $email),
            ];
        });

        // Internal heartbeat endpoint - generous limit for self-monitoring
        RateLimiter::for('heartbeat', function (Request $request) {
            // Allow 30 per minute from any IP (health checks run every 5 min)
            return Limit::perMinute(30)->by($request->ip());
        });
    }
}
