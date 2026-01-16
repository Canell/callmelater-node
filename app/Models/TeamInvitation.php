<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $team_id
 * @property string $email
 * @property string $role
 * @property string $token
 * @property string $invited_by
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property-read Team $team
 * @property-read User $inviter
 */
class TeamInvitation extends Model
{
    use HasUuids;

    protected $fillable = [
        'team_id',
        'email',
        'role',
        'token',
        'invited_by',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * The team this invitation is for.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The user who sent the invitation.
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Check if the invitation is still valid.
     */
    public function isValid(): bool
    {
        return ! $this->accepted_at && $this->expires_at->isFuture();
    }

    /**
     * Check if the invitation has been accepted.
     */
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    /**
     * Check if the invitation has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Generate a unique invitation token.
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Create a new invitation for a team.
     */
    public static function createForTeam(Team $team, string $email, User $inviter, string $role = 'member'): self
    {
        return self::create([
            'team_id' => $team->id,
            'email' => strtolower($email),
            'role' => $role,
            'token' => self::generateToken(),
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * Accept the invitation and add user to team.
     */
    public function accept(User $user): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        // Load the team with its account if not already loaded
        $this->loadMissing('team');

        // Update user's account to match the team's account
        // This allows them to access the team and its resources
        if ($user->account_id !== $this->team->account_id) {
            $user->update(['account_id' => $this->team->account_id]);
        }

        // Add user to the team
        $this->team->members()->attach($user->id, ['role' => $this->role]);

        // Mark invitation as accepted
        $this->update(['accepted_at' => now()]);

        return true;
    }
}
