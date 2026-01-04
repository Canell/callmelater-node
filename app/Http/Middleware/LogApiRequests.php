<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequests
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $this->log($request, $response, $startTime);

        return $response;
    }

    private function log(Request $request, Response $response, float $startTime): void
    {
        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        $context = [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        // Add user ID if authenticated
        if ($request->user()) {
            $context['user_id'] = $request->user()->id;
        }

        // Add token ID if using API token
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $context['token_id'] = $token->id;
        }

        // Log sensitive endpoints with more detail
        if ($this->isSensitiveEndpoint($request)) {
            $context['query_params'] = $request->query();
            // Don't log full body for security, just presence
            $context['has_body'] = ! empty($request->all());
        }

        // Log errors with more context
        if ($response->getStatusCode() >= 400) {
            Log::channel('api')->warning('API request failed', $context);
        } else {
            Log::channel('api')->info('API request', $context);
        }
    }

    private function isSensitiveEndpoint(Request $request): bool
    {
        $sensitivePatterns = [
            'api/tokens',
            'api/v1/actions',
        ];

        foreach ($sensitivePatterns as $pattern) {
            if (str_contains($request->path(), $pattern)) {
                return true;
            }
        }

        return false;
    }
}
