<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $account_id
 * @property int|null $created_by_user_id
 * @property string $name
 * @property array<int, array<string, mixed>> $steps
 * @property array<string, mixed>|null $input
 * @property array<string, mixed>|null $context
 * @property string $status
 * @property int $current_step
 * @property string $error_handling
 * @property string|null $failure_reason
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Account $account
 * @property-read User|null $creator
 */
class ActionChain extends Model
{
    use HasFactory;
    use HasUuids;

    // Chain statuses
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    // Error handling strategies
    public const ERROR_FAIL_CHAIN = 'fail_chain';

    public const ERROR_SKIP_STEP = 'skip_step';

    // Step types
    public const STEP_HTTP_CALL = 'http_call';

    public const STEP_GATED = 'gated';

    public const STEP_DELAY = 'delay';

    protected $fillable = [
        'account_id',
        'created_by_user_id',
        'name',
        'steps',
        'input',
        'context',
        'status',
        'current_step',
        'error_handling',
        'failure_reason',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'input' => 'array',
            'context' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // ========================================
    // Relationships
    // ========================================

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ScheduledAction::class, 'chain_id')->orderBy('chain_step');
    }

    public function currentAction(): HasOne
    {
        return $this->hasOne(ScheduledAction::class, 'chain_id')
            ->where('chain_step', $this->current_step);
    }

    // ========================================
    // Step Accessors
    // ========================================

    /**
     * Get the definition for a specific step.
     *
     * @return array<string, mixed>|null
     */
    public function getStepDefinition(int $stepIndex): ?array
    {
        return $this->steps[$stepIndex] ?? null;
    }

    /**
     * Get the response stored for a specific step.
     *
     * @return array<string, mixed>|null
     */
    public function getStepResponse(int $stepIndex): ?array
    {
        return $this->context['steps'][$stepIndex]['response'] ?? null;
    }

    /**
     * Get the status of a specific step from context.
     */
    public function getStepStatus(int $stepIndex): ?string
    {
        return $this->context['steps'][$stepIndex]['status'] ?? null;
    }

    /**
     * Get the total number of steps in this chain.
     */
    public function getTotalSteps(): int
    {
        return count($this->steps);
    }

    /**
     * Check if all steps have been completed.
     */
    public function isComplete(): bool
    {
        return $this->current_step >= $this->getTotalSteps();
    }

    /**
     * Check if there are more steps to execute.
     */
    public function hasNextStep(): bool
    {
        return $this->current_step < $this->getTotalSteps() - 1;
    }

    /**
     * Get the next step index.
     */
    public function getNextStepIndex(): int
    {
        return $this->current_step + 1;
    }

    // ========================================
    // Context Management
    // ========================================

    /**
     * Store the response from a completed step.
     *
     * @param  array<string, mixed>  $response
     */
    public function storeStepResponse(int $stepIndex, string $status, array $response = []): void
    {
        $context = $this->context ?? [];
        $context['steps'] = $context['steps'] ?? [];
        $context['steps'][$stepIndex] = [
            'status' => $status,
            'response' => $response,
            'completed_at' => now()->toIso8601String(),
        ];
        $this->context = $context;
        $this->save();
    }

    /**
     * Get a value from the chain input.
     */
    public function getInput(string $key, mixed $default = null): mixed
    {
        return data_get($this->input, $key, $default);
    }

    // ========================================
    // Status Management
    // ========================================

    /**
     * Check if chain is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ], true);
    }

    /**
     * Check if chain is currently running.
     */
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * Check if chain is pending (not started).
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Mark chain as running.
     */
    public function markRunning(): void
    {
        $this->status = self::STATUS_RUNNING;
        $this->started_at = now();
        $this->save();
    }

    /**
     * Mark chain as completed.
     */
    public function markCompleted(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Mark chain as failed.
     */
    public function markFailed(string $reason): void
    {
        $this->status = self::STATUS_FAILED;
        $this->failure_reason = $reason;
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Mark chain as cancelled.
     */
    public function markCancelled(): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Advance to the next step.
     */
    public function advanceStep(): void
    {
        $this->current_step++;
        $this->save();
    }

    // ========================================
    // Error Handling
    // ========================================

    /**
     * Check if chain should fail when a step fails.
     */
    public function shouldFailOnError(): bool
    {
        return $this->error_handling === self::ERROR_FAIL_CHAIN;
    }

    /**
     * Check if chain should skip failed steps and continue.
     */
    public function shouldSkipOnError(): bool
    {
        return $this->error_handling === self::ERROR_SKIP_STEP;
    }

    // ========================================
    // Scopes
    // ========================================

    public function scopeForAccount($query, string $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeRunning($query)
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeNotTerminal($query)
    {
        return $query->whereNotIn('status', [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ]);
    }
}
