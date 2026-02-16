<?php

namespace CallMeLater\Laravel\Http\Middleware;

use CallMeLater\Laravel\CallMeLater;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCallMeLaterSignature
{
    protected CallMeLater $callMeLater;

    public function __construct(CallMeLater $callMeLater)
    {
        $this->callMeLater = $callMeLater;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $this->callMeLater->verifySignature($request);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Invalid signature',
                'message' => $e->getMessage(),
            ], 401);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => 'Configuration error',
                'message' => $e->getMessage(),
            ], 500);
        }

        return $next($request);
    }
}
