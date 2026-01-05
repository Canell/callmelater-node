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

        // Public status page rate limit (generous, cached anyway)
        RateLimiter::for('status', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });
    }
}
