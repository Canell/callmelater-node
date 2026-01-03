<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduledAction extends Model
{
    use HasUuids;

    // Action types
    public const TYPE_HTTP = 'http';
    public const TYPE_REMINDER = 'reminder';

    // Intent types
    public const INTENT_ABSOLUTE = 'absolute';
    public const INTENT_WALL_CLOCK = 'wall_clock';

    // Resolution statuses
    public const STATUS_PENDING_RESOLUTION = 'pending_resolution';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_AWAITING_RESPONSE = 'awaiting_response';
    public const STATUS_EXECUTED = 'executed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FAILED = 'failed';

    // Confirmation modes
    public const CONFIRMATION_FIRST_RESPONSE = 'first_response';
    public const CONFIRMATION_ALL_REQUIRED = 'all_required';

    protected $fillable = [
        'owner_user_id',
        'owner_team_id',
        'name',
        'description',
        'type',
        'intent_type',
        'intent_payload',
        'timezone',
        'resolution_status',
        'execute_at_utc',
        'executed_at_utc',
        'failure_reason',
        'http_request',
        'idempotency_key',
        'attempt_count',
        'max_attempts',
        'last_attempt_at',
        'next_retry_at',
        'retry_strategy',
        'message',
        'confirmation_mode',
        'escalation_rules',
        'snooze_count',
        'max_snoozes',
        'token_expires_at',
        'webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'intent_payload' => 'array',
            'http_request' => 'array',
            'escalation_rules' => 'array',
            'execute_at_utc' => 'datetime',
            'executed_at_utc' => 'datetime',
            'last_attempt_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'token_expires_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'owner_team_id');
    }

    public function deliveryAttempts(): HasMany
    {
        return $this->hasMany(DeliveryAttempt::class, 'action_id');
    }

    public function reminderEvents(): HasMany
    {
        return $this->hasMany(ReminderEvent::class, 'reminder_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(ReminderRecipient::class, 'action_id');
    }

    // Scopes
    public function scopeResolved($query)
    {
        return $query->where('resolution_status', self::STATUS_RESOLVED);
    }

    public function scopeDue($query)
    {
        return $query->where(function ($q) {
            $q->where('execute_at_utc', '<=', now())
              ->orWhere('next_retry_at', '<=', now());
        });
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('owner_user_id', $userId);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('owner_team_id', $teamId);
    }

    // Helpers
    public function isHttp(): bool
    {
        return $this->type === self::TYPE_HTTP;
    }

    public function isReminder(): bool
    {
        return $this->type === self::TYPE_REMINDER;
    }

    public function isResolved(): bool
    {
        return $this->resolution_status === self::STATUS_RESOLVED;
    }

    public function isExecuted(): bool
    {
        return $this->resolution_status === self::STATUS_EXECUTED;
    }

    public function canRetry(): bool
    {
        return $this->attempt_count < $this->max_attempts;
    }

    public function canSnooze(): bool
    {
        return $this->snooze_count < $this->max_snoozes;
    }
}
