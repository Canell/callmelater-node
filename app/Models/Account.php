<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;

/**
 * @property string $id
 * @property string $name
 * @property int $owner_id
 * @property string|null $manual_plan
 * @property \Carbon\Carbon|null $manual_plan_expires_at
 * @property string|null $manual_plan_reason
 * @property-read User $owner
 * @property-read Collection<int, User> $members
 */
class Account extends Model
{
    use Billable, HasUuids;

    public const PLAN_FREE = 'free';

    public const PLAN_PRO = 'pro';

    public const PLAN_BUSINESS = 'business';

    protected $fillable = [
        'name',
        'owner_id',
        'manual_plan',
        'manual_plan_expires_at',
        'manual_plan_reason',
    ];

    protected function casts(): array
    {
        return [
            'manual_plan_expires_at' => 'datetime',
        ];
    }

    /**
     * The owner of this account (manages billing).
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * All members of this account.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * All actions owned by this account.
     */
    public function actions(): HasMany
    {
        return $this->hasMany(ScheduledAction::class, 'account_id');
    }

    /**
     * All verified domains for this account.
     */
    public function domains(): HasMany
    {
        return $this->hasMany(VerifiedDomain::class, 'account_id');
    }

    /**
     * Audit log of manual plan overrides.
     */
    public function planOverrides(): HasMany
    {
        return $this->hasMany(AccountPlanOverride::class, 'account_id');
    }

    /**
     * Get the account's current plan name.
     *
     * Priority:
     * 1. Manual plan override (if set and not expired)
     * 2. Stripe subscription
     * 3. Free tier
     */
    public function getPlan(): string
    {
        // Check manual plan override first
        if ($this->hasActiveManualPlan()) {
            return $this->manual_plan;
        }

        // Fall back to Stripe subscription
        if (! $this->subscribed('default')) {
            return self::PLAN_FREE;
        }

        $subscription = $this->subscription('default');
        $priceId = $subscription?->stripe_price;

        // Check both monthly and annual price IDs for each plan
        $proPrices = [
            config('services.stripe.prices.pro_monthly'),
            config('services.stripe.prices.pro_annual'),
        ];
        $businessPrices = [
            config('services.stripe.prices.business_monthly'),
            config('services.stripe.prices.business_annual'),
        ];

        if (in_array($priceId, $proPrices, true)) {
            return self::PLAN_PRO;
        }

        if (in_array($priceId, $businessPrices, true)) {
            return self::PLAN_BUSINESS;
        }

        return self::PLAN_FREE;
    }

    /**
     * Check if the account has an active manual plan override.
     */
    public function hasActiveManualPlan(): bool
    {
        if (empty($this->manual_plan)) {
            return false;
        }

        // Check if the plan is valid
        if (! in_array($this->manual_plan, [self::PLAN_PRO, self::PLAN_BUSINESS], true)) {
            return false;
        }

        // Check expiration
        if ($this->manual_plan_expires_at && $this->manual_plan_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if the plan is manually managed (for UI display).
     */
    public function isPlanManuallyManaged(): bool
    {
        return $this->hasActiveManualPlan();
    }

    /**
     * Set a manual plan override with optional expiration.
     *
     * @param  string|null  $plan  'pro', 'business', or null to revoke
     * @param  \DateTimeInterface|string|null  $expiresAt  Optional expiration date
     * @param  string|null  $reason  Reason for the override
     * @param  User|null  $setBy  Admin user who set the override
     */
    public function setManualPlan(?string $plan, $expiresAt = null, ?string $reason = null, ?User $setBy = null): void
    {
        $previousPlan = $this->manual_plan;

        $this->update([
            'manual_plan' => $plan,
            'manual_plan_expires_at' => $expiresAt,
            'manual_plan_reason' => $reason,
        ]);

        // Log the override for audit trail
        AccountPlanOverride::create([
            'account_id' => $this->id,
            'plan' => $plan,
            'expires_at' => $expiresAt,
            'reason' => $reason,
            'set_by_user_id' => $setBy?->id,
            'action' => $plan ? 'set' : 'revoked',
        ]);
    }

    /**
     * Revoke any manual plan override.
     */
    public function revokeManualPlan(?string $reason = null, ?User $revokedBy = null): void
    {
        $this->setManualPlan(null, null, $reason, $revokedBy);
    }

    /**
     * Get all limits for the account's current plan.
     *
     * @return array<string, int>
     */
    public function getPlanLimits(): array
    {
        $plan = $this->getPlan();

        return config("callmelater.plans.{$plan}", config('callmelater.plans.free'));
    }

    /**
     * Get a specific limit for the account's current plan.
     */
    public function getPlanLimit(string $key, int $default = 0): int
    {
        $limits = $this->getPlanLimits();

        return $limits[$key] ?? $default;
    }

    /**
     * Check if a user is an owner or admin of this account.
     */
    public function userCanManage(User $user): bool
    {
        if ($this->owner_id === $user->id) {
            return true;
        }

        $membership = $this->members()->where('user_id', $user->id)->first();

        return $membership && in_array($membership->pivot->role, ['owner', 'admin']); // @phpstan-ignore-line
    }

    /**
     * Check if a user is a member of this account.
     */
    public function userIsMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Get the number of SMS recipients used this month.
     *
     * Counts phone number recipients from reminders that include SMS channel.
     */
    public function getSmsUsageThisMonth(): int
    {
        $startOfMonth = now()->startOfMonth();

        // Get all reminder actions with SMS channel created this month
        $actions = $this->actions()
            ->where('type', ScheduledAction::TYPE_REMINDER)
            ->where('created_at', '>=', $startOfMonth)
            ->whereRaw("JSON_CONTAINS(escalation_rules->'$.channels', '\"sms\"')")
            ->get();

        $smsCount = 0;

        foreach ($actions as $action) {
            $recipients = $action->escalation_rules['recipients'] ?? [];
            foreach ($recipients as $recipient) {
                if ($this->isPhoneNumber($recipient)) {
                    $smsCount++;
                }
            }
        }

        return $smsCount;
    }

    /**
     * Check if a value is a phone number.
     */
    private function isPhoneNumber(string $value): bool
    {
        // Simple check for phone numbers (starts with + or contains only digits, spaces, dashes)
        return preg_match('/^\+?[\d\s\-\(\)]+$/', $value) === 1 && strlen(preg_replace('/\D/', '', $value)) >= 10;
    }
}
