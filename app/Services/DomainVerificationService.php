<?php

namespace App\Services;

use App\Models\User;
use App\Models\VerifiedDomain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DomainVerificationService
{
    // Thresholds that trigger verification requirement
    public const DAILY_THRESHOLD = 10;

    public const MONTHLY_THRESHOLD = 100;

    /**
     * Check if a domain requires verification for a user.
     *
     * @return array{required: bool, domain: ?string, token: ?string, reason: ?string}
     */
    public function checkVerificationRequired(User $user, string $url): array
    {
        $domain = VerifiedDomain::normalizeDomain($url);

        // Check if already verified (per account)
        $verified = VerifiedDomain::where('account_id', $user->account_id)
            ->where('domain', $domain)
            ->first();

        if ($verified && $verified->canExecuteActions()) {
            return [
                'required' => false,
                'domain' => $domain,
                'token' => null,
                'reason' => null,
            ];
        }

        // Check usage thresholds
        $usage = $this->getDomainUsage($user, $domain);

        if ($usage['daily'] < self::DAILY_THRESHOLD && $usage['monthly'] < self::MONTHLY_THRESHOLD) {
            return [
                'required' => false,
                'domain' => $domain,
                'token' => null,
                'reason' => null,
            ];
        }

        // Verification required - get or create verification record
        $verification = $this->getOrCreateVerification($user, $domain);

        $reason = $usage['daily'] >= self::DAILY_THRESHOLD
            ? 'daily_threshold_exceeded'
            : 'monthly_threshold_exceeded';

        return [
            'required' => true,
            'domain' => $domain,
            'token' => $verification->verification_token,
            'reason' => $reason,
        ];
    }

    /**
     * Get domain usage counts.
     *
     * @return array{daily: int, monthly: int}
     */
    public function getDomainUsage(User $user, string $domain): array
    {
        $dailyCount = DB::table('scheduled_actions')
            ->where('account_id', $user->account_id)
            ->where('created_at', '>=', now()->subDay())
            ->whereRaw("request->>'url' LIKE ?", ["%{$domain}%"])
            ->count();

        $monthlyCount = DB::table('scheduled_actions')
            ->where('account_id', $user->account_id)
            ->where('created_at', '>=', now()->subMonth())
            ->whereRaw("request->>'url' LIKE ?", ["%{$domain}%"])
            ->count();

        return [
            'daily' => $dailyCount,
            'monthly' => $monthlyCount,
        ];
    }

    /**
     * Get or create a verification record.
     */
    public function getOrCreateVerification(User $user, string $domain): VerifiedDomain
    {
        $domain = VerifiedDomain::normalizeDomain($domain);

        return VerifiedDomain::firstOrCreate(
            [
                'account_id' => $user->account_id,
                'domain' => $domain,
            ],
            [
                'verification_token' => VerifiedDomain::generateToken(),
            ]
        );
    }

    /**
     * Verify a domain using DNS TXT record.
     */
    public function verifyViaDns(VerifiedDomain $verification): bool
    {
        try {
            $records = dns_get_record($verification->domain, DNS_TXT);

            if ($records === false) {
                return false;
            }

            $expectedToken = "callmelater-verification={$verification->verification_token}";

            foreach ($records as $record) {
                if (isset($record['txt']) && $record['txt'] === $expectedToken) {
                    $verification->markAsVerified(VerifiedDomain::METHOD_DNS);
                    Log::info('Domain verified via DNS', [
                        'domain' => $verification->domain,
                        'account_id' => $verification->account_id,
                    ]);

                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            Log::warning('DNS verification failed', [
                'domain' => $verification->domain,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Verify a domain using HTTP file.
     */
    public function verifyViaFile(VerifiedDomain $verification): bool
    {
        try {
            $url = "https://{$verification->domain}/.well-known/callmelater.txt";

            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->get($url);

            if (! $response->successful()) {
                return false;
            }

            $expectedContent = "callmelater-verification={$verification->verification_token}";
            $actualContent = trim($response->body());

            if ($actualContent === $expectedContent) {
                $verification->markAsVerified(VerifiedDomain::METHOD_FILE);
                Log::info('Domain verified via file', [
                    'domain' => $verification->domain,
                    'account_id' => $verification->account_id,
                ]);

                return true;
            }

            return false;
        } catch (\Throwable $e) {
            Log::warning('File verification failed', [
                'domain' => $verification->domain,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Attempt verification using both methods.
     */
    public function verify(VerifiedDomain $verification): bool
    {
        // Try DNS first (recommended)
        if ($this->verifyViaDns($verification)) {
            return true;
        }

        // Fall back to file
        return $this->verifyViaFile($verification);
    }

    /**
     * Get all domains for a user's account.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, VerifiedDomain>
     */
    public function getDomainsForUser(User $user)
    {
        return VerifiedDomain::where('account_id', $user->account_id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

}
