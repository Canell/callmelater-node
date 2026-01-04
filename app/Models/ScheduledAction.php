<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int|null $owner_user_id
 * @property string|null $owner_team_id
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property string $intent_type
 * @property array<string, mixed>|null $intent_payload
 * @property string|null $timezone
 * @property string $resolution_status
 * @property Carbon|null $execute_at_utc
 * @property Carbon|null $executed_at_utc
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $http_request
 * @property string|null $idempotency_key
 * @property int $attempt_count
 * @property int $max_attempts
 * @property Carbon|null $last_attempt_at
 * @property Carbon|null $next_retry_at
 * @property string|null $retry_strategy
 * @property string|null $message
 * @property string|null $confirmation_mode
 * @property array<string, mixed>|null $escalation_rules
 * @property int $snooze_count
 * @property int $max_snoozes
 * @property Carbon|null $token_expires_at
 * @property string|null $webhook_secret
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
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
    public const STATUS_EXECUTING = 'executing';
    public const STATUS_AWAITING_RESPONSE = 'awaiting_response';
    public const STATUS_EXECUTED = 'executed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FAILED = 'failed';

    // Terminal statuses (no further transitions allowed)
    public const TERMINAL_STATUSES = [
        self::STATUS_EXECUTED,
        self::STATUS_CANCELLED,
        self::STATUS_EXPIRED,
        self::STATUS_FAILED,
    ];

    // Valid state transitions
    private const VALID_TRANSITIONS = [
        self::STATUS_PENDING_RESOLUTION => [self::STATUS_RESOLVED, self::STATUS_CANCELLED],
        self::STATUS_RESOLVED => [self::STATUS_EXECUTING, self::STATUS_CANCELLED],
        self::STATUS_EXECUTING => [self::STATUS_EXECUTED, self::STATUS_FAILED, self::STATUS_AWAITING_RESPONSE, self::STATUS_RESOLVED],
        self::STATUS_AWAITING_RESPONSE => [self::STATUS_EXECUTED, self::STATUS_EXPIRED, self::STATUS_PENDING_RESOLUTION, self::STATUS_CANCELLED],
        self::STATUS_EXECUTED => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_EXPIRED => [],
        self::STATUS_FAILED => [],
    ];

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

    // ========================================
    // State Machine Methods
    // ========================================

    /**
     * Check if a transition to the given status is valid.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $allowedTransitions = self::VALID_TRANSITIONS[$this->resolution_status] ?? [];
        return in_array($newStatus, $allowedTransitions, true);
    }

    /**
     * Transition to a new status with validation.
     *
     * @throws \InvalidArgumentException if transition is not allowed
     */
    private function transitionTo(string $newStatus): void
    {
        if (!$this->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                "Invalid state transition from '{$this->resolution_status}' to '{$newStatus}' for action {$this->id}"
            );
        }

        $this->resolution_status = $newStatus;
    }

    /**
     * Check if this action can be executed (picked up by dispatcher).
     */
    public function canBeExecuted(): bool
    {
        return $this->resolution_status === self::STATUS_RESOLVED;
    }

    /**
     * Check if this action is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this->resolution_status, self::TERMINAL_STATUSES, true);
    }

    /**
     * Check if this action is currently being executed.
     */
    public function isExecuting(): bool
    {
        return $this->resolution_status === self::STATUS_EXECUTING;
    }

    /**
     * Check if this action can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return $this->canTransitionTo(self::STATUS_CANCELLED);
    }

    /**
     * Mark action as executing (dispatcher picked it up).
     * This is a LOCK - prevents other workers from selecting it.
     */
    public function markAsExecuting(): void
    {
        $this->transitionTo(self::STATUS_EXECUTING);
        $this->save();
    }

    /**
     * Mark action as successfully executed.
     */
    public function markAsExecuted(): void
    {
        $this->transitionTo(self::STATUS_EXECUTED);
        $this->executed_at_utc = now();
        $this->save();
    }

    /**
     * Mark action as failed (terminal - no more retries).
     */
    public function markAsFailed(string $reason): void
    {
        $this->transitionTo(self::STATUS_FAILED);
        $this->failure_reason = $reason;
        $this->save();
    }

    /**
     * Mark reminder as awaiting human response.
     */
    public function markAsAwaitingResponse(int $tokenExpiryDays = 7): void
    {
        $this->transitionTo(self::STATUS_AWAITING_RESPONSE);
        $this->token_expires_at = now()->addDays($tokenExpiryDays);
        $this->save();
    }

    /**
     * Mark action as expired (reminder timeout).
     */
    public function markAsExpired(): void
    {
        $this->transitionTo(self::STATUS_EXPIRED);
        $this->save();
    }

    /**
     * Cancel this action.
     *
     * @throws \InvalidArgumentException if action cannot be cancelled
     */
    public function cancel(): void
    {
        if (!$this->canBeCancelled()) {
            throw new \InvalidArgumentException(
                "Cannot cancel action in '{$this->resolution_status}' state"
            );
        }

        $this->transitionTo(self::STATUS_CANCELLED);
        $this->save();
    }

    // ========================================
    // Retry Logic (centralized)
    // ========================================

    /**
     * Check if this action should be retried after a failure.
     */
    public function shouldRetry(): bool
    {
        return $this->attempt_count < $this->max_attempts;
    }

    /**
     * Schedule the next retry attempt.
     * Returns action to RESOLVED state with next_retry_at set.
     */
    public function scheduleNextRetry(): void
    {
        if (!$this->shouldRetry()) {
            $this->markAsFailed('Max retry attempts reached');
            return;
        }

        $delay = $this->calculateRetryDelay();
        $this->resolution_status = self::STATUS_RESOLVED; // Back to resolved for re-pickup
        $this->next_retry_at = now()->addSeconds($delay);
        $this->save();
    }

    /**
     * Record an execution attempt.
     */
    public function recordAttempt(): void
    {
        $this->attempt_count++;
        $this->last_attempt_at = now();
        $this->save();
    }

    /**
     * Calculate retry delay based on strategy and attempt count.
     */
    private function calculateRetryDelay(): int
    {
        // Exponential backoff delays: 1min, 5min, 15min, 1hr, 4hr
        $exponentialDelays = [60, 300, 900, 3600, 14400];

        if ($this->retry_strategy === 'linear') {
            return 300 * $this->attempt_count; // 5 min increments
        }

        // Exponential (default)
        $index = min($this->attempt_count, count($exponentialDelays) - 1);
        return $exponentialDelays[$index];
    }
}
