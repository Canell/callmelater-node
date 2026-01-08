<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;

class Account extends Model
{
    use HasUuids, Billable;

    protected $fillable = [
        'name',
        'owner_id',
    ];

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
     * Get the account's current plan name.
     */
    public function getPlan(): string
    {
        if (! $this->subscribed('default')) {
            return 'free';
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
            return 'pro';
        }

        if (in_array($priceId, $businessPrices, true)) {
            return 'business';
        }

        return 'free';
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

        return $membership && in_array($membership->pivot->role, ['owner', 'admin']);
    }

    /**
     * Check if a user is a member of this account.
     */
    public function userIsMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }
}
