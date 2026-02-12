<?php

namespace App\Models;

use App\Mail\ActionFailedMail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Mail;

/**
 * @property string $id
 * @property string $account_id
 * @property string|null $chain_id
 * @property int|null $chain_step
 * @property int|null $created_by_user_id
 * @property string $name
 * @property string|null $description
 * @property string $mode
 * @property string $intent_type
 * @property array<string, mixed>|null $intent_payload
 * @property string|null $timezone
 * @property string $resolution_status
 * @property Carbon|null $execute_at_utc
 * @property Carbon|null $executed_at_utc
 * @property Carbon|null $gate_passed_at
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $request
 * @property array<string, mixed>|null $gate
 * @property bool $notify_creator_on_response
 * @property string|null $idempotency_key
 * @property int $attempt_count
 * @property int $max_attempts
 * @property Carbon|null $last_attempt_at
 * @property Carbon|null $next_retry_at
 * @property string|null $retry_strategy
 * @property int $snooze_count
 * @property Carbon|null $token_expires_at
 * @property string|null $webhook_secret
 * @property string|null $callback_url
 * @property string|null $current_execution_cycle_id
 * @property int $manual_retry_count
 * @property Carbon|null $last_manual_retry_at
 * @property string|null $replaced_by_action_id
 * @property array<string, mixed>|null $coordination_config
 * @property int $coordination_reschedule_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Account|null $account
 * @property-read array<string> $coordination_keys
 * @property-read ScheduledAction|null $replacedBy
 */
class ScheduledAction extends Model
{
    use HasFactory;
    use HasUuids;

    // Action modes
    public const MODE_IMMEDIATE = 'immediate';

    public const MODE_GATED = 'gated';

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
        self::STATUS_AWAITING_RESPONSE => [self::STATUS_EXECUTED, self::STATUS_EXPIRED, self::STATUS_PENDING_RESOLUTION, self::STATUS_CANCELLED, self::STATUS_RESOLVED],
        self::STATUS_EXECUTED => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_EXPIRED => [],
        self::STATUS_FAILED => [self::STATUS_RESOLVED], // Allow manual retry
    ];

    // Confirmation modes (used within gate config)
    public const CONFIRMATION_FIRST_RESPONSE = 'first_response';

    public const CONFIRMATION_ALL_REQUIRED = 'all_required';

    protected $fillable = [
        'account_id',
        'chain_id',
        'chain_step',
        'created_by_user_id',
        'template_id',
        'name',
        'description',
        'mode',
        'intent_type',
        'intent_payload',
        'timezone',
        'resolution_status',
        'execute_at_utc',
        'executed_at_utc',
        'gate_passed_at',
        'failure_reason',
        'request',
        'gate',
        'notify_creator_on_response',
        'idempotency_key',
        'attempt_count',
        'max_attempts',
        'last_attempt_at',
        'next_retry_at',
        'retry_strategy',
        'snooze_count',
        'token_expires_at',
        'webhook_secret',
        'callback_url',
        'current_execution_cycle_id',
        'manual_retry_count',
        'last_manual_retry_at',
        'replaced_by_action_id',
        'coordination_config',
        'coordination_reschedule_count',
    ];

    protected function casts(): array
    {
        return [
            'intent_payload' => 'array',
            'request' => 'array',
            'gate' => 'array',
            'notify_creator_on_response' => 'boolean',
            'coordination_config' => 'array',
            'execute_at_utc' => 'datetime',
            'executed_at_utc' => 'datetime',
            'gate_passed_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'last_manual_retry_at' => 'datetime',
        ];
    }

    /**
     * The account that owns this action.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * The chain this action belongs to (if any).
     */
    public function chain(): BelongsTo
    {
        return $this->belongsTo(ActionChain::class);
    }

    /**
     * Check if this action is part of a chain.
     */
    public function isChainStep(): bool
    {
        return $this->chain_id !== null;
    }

    /**
     * The template this action was created from (if any).
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ActionTemplate::class, 'template_id');
    }

    /**
     * The user who created this action (optional, for audit trail).
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Alias for creator relationship.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the owner of this action (creator if set, otherwise account owner).
     */
    public function getOwnerAttribute(): ?User
    {
        return $this->creator ?? $this->account?->owner; // @phpstan-ignore-line
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

    public function callbackAttempts(): HasMany
    {
        return $this->hasMany(CallbackAttempt::class, 'action_id');
    }

    public function executionCycles(): HasMany
    {
        return $this->hasMany(ExecutionCycle::class, 'action_id');
    }

    public function currentExecutionCycle(): BelongsTo
    {
        return $this->belongsTo(ExecutionCycle::class, 'current_execution_cycle_id');
    }

    public function coordinationKeyRecords(): HasMany
    {
        return $this->hasMany(ActionCoordinationKey::class, 'action_id');
    }

    /**
     * The action that replaced this one (if cancelled via coordination).
     */
    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(ScheduledAction::class, 'replaced_by_action_id');
    }

    /**
     * Actions that this one replaced (cancelled via coordination).
     */
    public function replacements(): HasMany
    {
        return $this->hasMany(ScheduledAction::class, 'replaced_by_action_id');
    }

    /**
     * Get the coordination keys as an array of strings.
     *
     * @return array<string>
     */
    public function getCoordinationKeysAttribute(): array
    {
        return $this->coordinationKeyRecords->pluck('coordination_key')->toArray();
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

    public function scopeForAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    // ========================================
    // Mode Helpers
    // ========================================

    /**
     * Check if this is an immediate action (executes automatically at scheduled time).
     */
    public function isImmediate(): bool
    {
        return $this->mode === self::MODE_IMMEDIATE;
    }

    /**
     * Check if this is a gated action (requires human approval).
     */
    public function isGated(): bool
    {
        return $this->mode === self::MODE_GATED;
    }

    /**
     * Check if this action has an HTTP request configured.
     */
    public function hasRequest(): bool
    {
        return ! empty($this->request);
    }

    /**
     * Check if the gate has been passed (approved).
     */
    public function gatePassed(): bool
    {
        return $this->gate_passed_at !== null;
    }

    /**
     * Get the gate message.
     */
    public function getGateMessage(): ?string
    {
        return $this->gate['message'] ?? null;
    }

    /**
     * Get the gate recipients.
     *
     * @return array<string>
     */
    public function getGateRecipients(): array
    {
        return $this->gate['recipients'] ?? [];
    }

    /**
     * Get the gate channels (additional channels like Teams/Slack).
     * Email/SMS are auto-detected from recipient types, not stored here.
     *
     * @return array<string>
     */
    public function getGateChannels(): array
    {
        return $this->gate['channels'] ?? [];
    }

    /**
     * Get the gate timeout string (e.g., "4h", "7d").
     */
    public function getGateTimeout(): string
    {
        return $this->gate['timeout'] ?? '7d';
    }

    /**
     * Get the timeout behavior (cancel, expire, approve).
     */
    public function getGateOnTimeout(): string
    {
        return $this->gate['on_timeout'] ?? 'expire';
    }

    /**
     * Get max snoozes from gate config.
     */
    public function getMaxSnoozes(): int
    {
        return $this->gate['max_snoozes'] ?? 5;
    }

    /**
     * Get confirmation mode from gate config.
     */
    public function getConfirmationMode(): string
    {
        return $this->gate['confirmation_mode'] ?? self::CONFIRMATION_FIRST_RESPONSE;
    }

    /**
     * Get escalation config from gate.
     *
     * @return array{after_hours: float|null, contacts: array<string>}|null
     */
    public function getEscalation(): ?array
    {
        return $this->gate['escalation'] ?? null;
    }

    /**
     * Get attachments from gate config.
     *
     * @return array<array{url: string, name: string|null}>
     */
    public function getGateAttachments(): array
    {
        return $this->gate['attachments'] ?? [];
    }

    // ========================================
    // Status Helpers
    // ========================================

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
        return $this->snooze_count < $this->getMaxSnoozes();
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
        if (! $this->canTransitionTo($newStatus)) {
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
     * Check if this action can be manually retried.
     */
    public function canBeManuallyRetried(): bool
    {
        return $this->resolution_status === self::STATUS_FAILED;
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

        // Send failure notification to action owner
        $this->sendFailureNotification();
    }

    /**
     * Send email notification to action owner about failure.
     */
    private function sendFailureNotification(): void
    {
        $owner = $this->owner;
        if ($owner && $owner->email) {
            Mail::to($owner->email)->queue(new ActionFailedMail($this));
        }
    }

    /**
     * Mark action as awaiting human response.
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
        if (! $this->canBeCancelled()) {
            throw new \InvalidArgumentException(
                "Cannot cancel action in '{$this->resolution_status}' state"
            );
        }

        $this->transitionTo(self::STATUS_CANCELLED);
        $this->save();
    }

    /**
     * Cancel this action and mark it as replaced by another action.
     *
     * @throws \InvalidArgumentException if action cannot be cancelled
     */
    public function cancelAndReplace(string $replacementActionId): void
    {
        if (! $this->canBeCancelled()) {
            throw new \InvalidArgumentException(
                "Cannot cancel action in '{$this->resolution_status}' state"
            );
        }

        $this->transitionTo(self::STATUS_CANCELLED);
        $this->replaced_by_action_id = $replacementActionId;
        $this->save();
    }

    /**
     * Mark the gate as passed and prepare for HTTP execution.
     */
    public function passGate(): void
    {
        $this->gate_passed_at = now();
        if ($this->hasRequest()) {
            $this->transitionTo(self::STATUS_RESOLVED);
            $this->execute_at_utc = now();
        } else {
            $this->transitionTo(self::STATUS_EXECUTED);
            $this->executed_at_utc = now();
        }
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
        if (! $this->shouldRetry()) {
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

    // ========================================
    // Timeout Parsing Helper
    // ========================================

    /**
     * Parse a timeout string (e.g., "4h", "7d") to days.
     */
    public static function parseTimeoutToDays(string $timeout): int
    {
        if (preg_match('/^(\d+)([hdw])$/', $timeout, $matches)) {
            $value = (int) $matches[1];
            $unit = $matches[2];

            return match ($unit) {
                'h' => max(1, (int) ceil($value / 24)),
                'w' => $value * 7,
                default => $value, // 'd'
            };
        }

        return 7; // Default to 7 days
    }

    // ========================================
    // Coordination Helpers
    // ========================================

    /**
     * Get the on_execute condition from coordination config.
     */
    public function getOnExecuteCondition(): ?string
    {
        return $this->coordination_config['on_execute']['condition'] ?? null;
    }

    /**
     * Get the behavior when on_execute condition is not met.
     */
    public function getOnConditionNotMet(): string
    {
        return $this->coordination_config['on_execute']['on_condition_not_met'] ?? 'cancel';
    }

    /**
     * Get the reschedule delay in seconds.
     */
    public function getRescheduleDelay(): int
    {
        return $this->coordination_config['on_execute']['reschedule_delay'] ?? 300; // 5 minutes default
    }

    /**
     * Get the max reschedules limit.
     */
    public function getMaxReschedules(): int
    {
        return $this->coordination_config['on_execute']['max_reschedules'] ?? 10;
    }

    /**
     * Check if this action has coordination keys loaded.
     */
    public function hasCoordinationKeys(): bool
    {
        return ! empty($this->coordination_keys);
    }
}
