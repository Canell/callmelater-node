<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VerifiedDomain extends Model
{
    use HasUuids;

    public const METHOD_DNS = 'dns';

    public const METHOD_FILE = 'file';

    // Domain verification is now permanent (no expiration)

    protected $fillable = [
        'account_id',
        'domain',
        'verification_token',
        'method',
        'verified_at',
        'expires_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Generate a new verification token.
     */
    public static function generateToken(): string
    {
        return 'clm_'.Str::random(16);
    }

    /**
     * Normalize a domain (lowercase, strip protocol and path).
     */
    public static function normalizeDomain(string $url): string
    {
        // Parse URL if it looks like one
        if (str_contains($url, '://')) {
            $parsed = parse_url($url);
            $domain = $parsed['host'] ?? $url;
        } else {
            // Remove any path
            $domain = explode('/', $url)[0];
        }

        return strtolower(trim($domain));
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Check if this domain is verified.
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Check if actions can be executed.
     */
    public function canExecuteActions(): bool
    {
        return $this->isVerified();
    }

    /**
     * Mark domain as verified (permanent, no expiration).
     */
    public function markAsVerified(string $method): void
    {
        $this->method = $method;
        $this->verified_at = now();
        $this->expires_at = null;
        $this->save();
    }
}
