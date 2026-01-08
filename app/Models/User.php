<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, Billable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'timezone',
        'webhook_secret',
        'notification_preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'notification_preferences' => 'array',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'owner_id');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ScheduledAction::class, 'owner_user_id');
    }

    /**
     * Get the user's current plan name.
     */
    public function getPlan(): string
    {
        if (! $this->subscribed('default')) {
            return 'free';
        }

        $subscription = $this->subscription('default');
        $priceId = $subscription?->stripe_price;

        return match ($priceId) {
            config('services.stripe.prices.pro') => 'pro',
            config('services.stripe.prices.business') => 'business',
            default => 'free',
        };
    }

    /**
     * Get all limits for the user's current plan.
     *
     * @return array<string, int>
     */
    public function getPlanLimits(): array
    {
        $plan = $this->getPlan();

        return config("callmelater.plans.{$plan}", config('callmelater.plans.free'));
    }

    /**
     * Get a specific limit for the user's current plan.
     */
    public function getPlanLimit(string $key, int $default = 0): int
    {
        $limits = $this->getPlanLimits();

        return $limits[$key] ?? $default;
    }
}
