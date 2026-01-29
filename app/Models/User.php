<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string $email
 * @property string|null $phone
 * @property string|null $password
 * @property \Carbon\Carbon|null $email_verified_at
 * @property bool $is_admin
 * @property string|null $timezone
 * @property string $webhook_secret
 * @property array|null $notification_preferences
 * @property string|null $account_id
 * @property \Carbon\Carbon|null $quota_warning_sent_at
 * @property-read Account|null $account
 * @property-read string $full_name
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'email_verified_at',
        'is_admin',
        'timezone',
        'webhook_secret',
        'notification_preferences',
        'account_id',
        'quota_warning_sent_at',
    ];

    protected $appends = ['full_name'];

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
            'quota_warning_sent_at' => 'datetime',
        ];
    }

    /**
     * Get the full name (first + last name, or fallback to name field).
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: function () {
                $fullName = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

                return $fullName ?: $this->name;
            }
        );
    }

    /**
     * Auto-create an account and webhook secret when a new user registers.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            // Generate webhook secret if not provided
            if (empty($user->webhook_secret)) {
                $user->webhook_secret = 'whsec_'.Str::random(32);
            }
        });

        static::created(function (User $user) {
            // Skip if user already has an account (e.g., invited to existing account)
            if ($user->account_id) {
                return;
            }

            // Create personal account for new user
            $account = Account::create([
                'name' => "{$user->name}'s Account",
                'owner_id' => $user->id,
            ]);

            $user->update(['account_id' => $account->id]);
            $account->members()->attach($user->id, ['role' => 'owner']);
        });
    }

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    /**
     * The account this user belongs to.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * All accounts the user is a member of (for potential future multi-account support).
     */
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the user's current plan name (proxy to account).
     */
    public function getPlan(): string
    {
        return $this->account?->getPlan() ?? 'free';
    }

    /**
     * Get all limits for the user's current plan (proxy to account).
     *
     * @return array<string, int>
     */
    public function getPlanLimits(): array
    {
        return $this->account?->getPlanLimits() ?? config('callmelater.plans.free');
    }

    /**
     * Get a specific limit for the user's current plan (proxy to account).
     */
    public function getPlanLimit(string $key, int $default = 0): int
    {
        return $this->account?->getPlanLimit($key, $default) ?? $default;
    }

    /**
     * Check if user can manage billing (owner or admin of their account).
     */
    public function canManageBilling(): bool
    {
        return $this->account?->userCanManage($this) ?? false;
    }
}
