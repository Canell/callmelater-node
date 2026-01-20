<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VerifiedDomain;
use App\Services\DomainVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function __construct(
        private DomainVerificationService $verificationService
    ) {}

    /**
     * List all domains for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $domains = $this->verificationService->getDomainsForUser($request->user());

        return response()->json([
            'data' => $domains->map(fn (VerifiedDomain $domain) => [
                'id' => $domain->id,
                'domain' => $domain->domain,
                'verified' => $domain->isVerified(),
                'verified_at' => $domain->verified_at?->toIso8601String(),
                'expires_at' => $domain->expires_at?->toIso8601String(),
                'days_until_expiry' => $domain->daysUntilExpiry(),
                'in_grace_period' => $domain->isInGracePeriod(),
                'method' => $domain->method,
                'verification_token' => $domain->verification_token,
            ]),
        ]);
    }

    /**
     * Get verification instructions for a domain.
     */
    public function show(Request $request, string $domain): JsonResponse
    {
        $normalizedDomain = VerifiedDomain::normalizeDomain($domain);

        $verification = $this->verificationService->getOrCreateVerification(
            $request->user(),
            $normalizedDomain
        );

        return response()->json([
            'domain' => $verification->domain,
            'verified' => $verification->isVerified(),
            'verified_at' => $verification->verified_at?->toIso8601String(),
            'expires_at' => $verification->expires_at?->toIso8601String(),
            'days_until_expiry' => $verification->daysUntilExpiry(),
            'verification_token' => $verification->verification_token,
            'verification_methods' => [
                'dns' => [
                    'type' => 'TXT record',
                    'value' => "callmelater-verification={$verification->verification_token}",
                    'instructions' => "Add this TXT record to your domain's DNS settings.",
                ],
                'file' => [
                    'url' => "https://{$verification->domain}/.well-known/callmelater.txt",
                    'content' => "callmelater-verification={$verification->verification_token}",
                    'instructions' => 'Create a file at the URL above with the specified content.',
                ],
            ],
        ]);
    }

    /**
     * Verify a domain.
     */
    public function verify(Request $request, string $domain): JsonResponse
    {
        $normalizedDomain = VerifiedDomain::normalizeDomain($domain);

        $verification = VerifiedDomain::where('account_id', $request->user()->account_id)
            ->where('domain', $normalizedDomain)
            ->first();

        if (! $verification) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'No verification record found for this domain.',
            ], 404);
        }

        // Already verified and not expired
        if ($verification->isVerified()) {
            return response()->json([
                'verified' => true,
                'message' => 'Domain is already verified.',
                'expires_at' => $verification->expires_at?->toIso8601String(),
            ]);
        }

        // Attempt verification
        $success = $this->verificationService->verify($verification);

        if ($success) {
            return response()->json([
                'verified' => true,
                'message' => 'Domain verified successfully.',
                'method' => $verification->method,
                'expires_at' => $verification->expires_at?->toIso8601String(),
            ]);
        }

        return response()->json([
            'verified' => false,
            'message' => 'Verification failed. Please ensure the DNS record or verification file is correctly configured.',
            'verification_token' => $verification->verification_token,
        ], 400);
    }

    /**
     * Delete a domain verification.
     */
    public function destroy(Request $request, string $domain): JsonResponse
    {
        $normalizedDomain = VerifiedDomain::normalizeDomain($domain);

        $verification = VerifiedDomain::where('account_id', $request->user()->account_id)
            ->where('domain', $normalizedDomain)
            ->first();

        if (! $verification) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'No verification record found for this domain.',
            ], 404);
        }

        $verification->delete();

        return response()->json([
            'message' => 'Domain verification removed.',
        ]);
    }
}
