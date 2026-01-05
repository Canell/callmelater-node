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

    public const VALIDITY_MONTHS = 12;
    public const GRACE_PERIOD_DAYS = 30;

    protected $fillable = [
        'user_id',
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
        return 'clm_' . Str::random(16);
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this domain is verified and not expired.
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null && !$this->isExpired();
    }

    /**
     * Check if the verification has expired.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Check if the domain is in grace period.
     */
    public function isInGracePeriod(): bool
    {
        if (!$this->isExpired()) {
            return false;
        }

        $graceEnd = $this->expires_at->addDays(self::GRACE_PERIOD_DAYS);

        return now()->isBefore($graceEnd);
    }

    /**
     * Check if actions can be executed (verified or in grace period).
     */
    public function canExecuteActions(): bool
    {
        return $this->isVerified() || $this->isInGracePeriod();
    }

    /**
     * Mark domain as verified.
     */
    public function markAsVerified(string $method): void
    {
        $this->method = $method;
        $this->verified_at = now();
        $this->expires_at = now()->addMonths(self::VALIDITY_MONTHS);
        $this->save();
    }

    /**
     * Days until expiry (negative if expired).
     */
    public function daysUntilExpiry(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        return (int) now()->diffInDays($this->expires_at, false);
    }
}
