<?php

namespace CallMeLater\Laravel\Http\Middleware;

use CallMeLater\Laravel\CallMeLater;
use CallMeLater\Laravel\Exceptions\ConfigurationException;
use CallMeLater\Laravel\Exceptions\SignatureVerificationException;
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
        } catch (SignatureVerificationException $e) {
            return response()->json([
                'error' => 'Invalid signature',
                'message' => $e->getMessage(),
            ], 401);
        } catch (ConfigurationException $e) {
            return response()->json([
                'error' => 'Configuration error',
                'message' => $e->getMessage(),
            ], 500);
        }

        return $next($request);
    }
}
