<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NotificationConsent extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_OPTED_IN = 'opted_in';
    public const STATUS_OPTED_OUT = 'opted_out';

    // Rate limits per recipient
    public const LIMIT_24H = 1;
    public const LIMIT_7D = 3;
    public const LIMIT_30D = 10;

    protected $fillable = [
        'email',
        'status',
        'consent_token',
        'consented_at',
        'revoked_at',
        'last_optin_sent_at',
        'optin_count_24h',
        'optin_count_7d',
        'optin_count_30d',
        'counters_reset_at',
        'suppressed',
        'suppression_reason',
    ];

    protected $casts = [
        'consented_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_optin_sent_at' => 'datetime',
        'counters_reset_at' => 'datetime',
        'suppressed' => 'boolean',
        'optin_count_24h' => 'integer',
        'optin_count_7d' => 'integer',
        'optin_count_30d' => 'integer',
    ];

    /**
     * Normalize an email address.
     */
    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * Generate a consent token.
     */
    public static function generateToken(): string
    {
        return Str::random(48);
    }

    /**
     * Find or create a consent record for an email.
     */
    public static function findOrCreateForEmail(string $email): self
    {
        $normalized = self::normalizeEmail($email);

        return self::firstOrCreate(
            ['email' => $normalized],
            [
                'consent_token' => self::generateToken(),
                'status' => self::STATUS_PENDING,
            ]
        );
    }

    /**
     * Check if reminders can be sent to this recipient.
     */
    public function canReceiveReminders(): bool
    {
        return $this->status === self::STATUS_OPTED_IN && !$this->suppressed;
    }

    /**
     * Check if an opt-in email can be sent (rate limit check).
     */
    public function canSendOptinEmail(): bool
    {
        // Never send to suppressed or opted-out
        if ($this->suppressed || $this->status === self::STATUS_OPTED_OUT) {
            return false;
        }

        // Already opted in - no need to send
        if ($this->status === self::STATUS_OPTED_IN) {
            return false;
        }

        // Check rate limits
        $this->refreshCountersIfNeeded();

        if ($this->optin_count_24h >= self::LIMIT_24H) {
            return false;
        }

        if ($this->optin_count_7d >= self::LIMIT_7D) {
            return false;
        }

        if ($this->optin_count_30d >= self::LIMIT_30D) {
            return false;
        }

        return true;
    }

    /**
     * Get the reason why opt-in email cannot be sent.
     */
    public function getOptinBlockReason(): ?string
    {
        if ($this->suppressed) {
            return 'recipient_suppressed';
        }

        if ($this->status === self::STATUS_OPTED_OUT) {
            return 'recipient_opted_out';
        }

        if ($this->status === self::STATUS_OPTED_IN) {
            return 'already_opted_in';
        }

        $this->refreshCountersIfNeeded();

        if ($this->optin_count_24h >= self::LIMIT_24H) {
            return 'rate_limit_24h';
        }

        if ($this->optin_count_7d >= self::LIMIT_7D) {
            return 'rate_limit_7d';
        }

        if ($this->optin_count_30d >= self::LIMIT_30D) {
            return 'rate_limit_30d';
        }

        return null;
    }

    /**
     * Record that an opt-in email was sent.
     */
    public function recordOptinEmailSent(): void
    {
        $this->refreshCountersIfNeeded();

        $this->last_optin_sent_at = now();
        $this->optin_count_24h++;
        $this->optin_count_7d++;
        $this->optin_count_30d++;
        $this->save();
    }

    /**
     * Refresh rate limit counters if they're stale.
     */
    protected function refreshCountersIfNeeded(): void
    {
        if ($this->last_optin_sent_at === null) {
            return;
        }

        $now = now();

        // Reset 24h counter if last email was more than 24h ago
        if ($this->last_optin_sent_at->diffInHours($now) >= 24) {
            $this->optin_count_24h = 0;
        }

        // Reset 7d counter if last email was more than 7 days ago
        if ($this->last_optin_sent_at->diffInDays($now) >= 7) {
            $this->optin_count_7d = 0;
        }

        // Reset 30d counter if last email was more than 30 days ago
        if ($this->last_optin_sent_at->diffInDays($now) >= 30) {
            $this->optin_count_30d = 0;
        }
    }

    /**
     * Accept reminders (opt-in).
     */
    public function optIn(): void
    {
        $this->status = self::STATUS_OPTED_IN;
        $this->consented_at = now();
        $this->revoked_at = null;
        $this->save();
    }

    /**
     * Decline/unsubscribe (opt-out).
     * This is reversible - user can re-subscribe later.
     */
    public function optOut(string $reason = 'user_declined'): void
    {
        $this->status = self::STATUS_OPTED_OUT;
        $this->revoked_at = now();
        // Note: We don't set suppressed = true here because user-initiated
        // opt-outs should be reversible. Use suppress() for permanent blocks.
        $this->save();
    }

    /**
     * Permanently suppress this email address.
     */
    public function suppress(string $reason): void
    {
        $this->suppressed = true;
        $this->suppression_reason = $reason;
        $this->save();
    }

    /**
     * Regenerate consent token.
     */
    public function regenerateToken(): void
    {
        $this->consent_token = self::generateToken();
        $this->save();
    }
}
