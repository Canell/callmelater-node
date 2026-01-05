<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class DomainVerificationRequiredException extends Exception
{
    public function __construct(
        public readonly string $domain,
        public readonly string $verificationToken,
        public readonly string $reason
    ) {
        parent::__construct("Domain verification required for {$domain}");
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'error' => 'domain_verification_required',
            'message' => 'Domain ownership verification is required before scheduling more actions to this domain.',
            'domain' => $this->domain,
            'verification_token' => $this->verificationToken,
            'reason' => $this->reason,
            'verification_methods' => [
                'dns' => [
                    'type' => 'TXT record',
                    'value' => "callmelater-verification={$this->verificationToken}",
                    'instructions' => "Add this TXT record to your domain's DNS settings.",
                ],
                'file' => [
                    'url' => "https://{$this->domain}/.well-known/callmelater.txt",
                    'content' => "callmelater-verification={$this->verificationToken}",
                    'instructions' => 'Create a file at the URL above with the specified content.',
                ],
            ],
            'verify_url' => "/api/v1/domains/{$this->domain}/verify",
        ], 403);
    }
}
