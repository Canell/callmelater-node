<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $action_id
 * @property int $cycle_number
 * @property string $triggered_by
 * @property int|null $triggered_by_user_id
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property string $result
 * @property string|null $failure_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ScheduledAction $action
 * @property-read User|null $triggeredByUser
 */
class ExecutionCycle extends Model
{
    use HasUuids;

    public const TRIGGERED_SYSTEM = 'system';

    public const TRIGGERED_MANUAL = 'manual';

    public const RESULT_SUCCESS = 'success';

    public const RESULT_FAILED = 'failed';

    public const RESULT_IN_PROGRESS = 'in_progress';

    protected $fillable = [
        'action_id',
        'cycle_number',
        'triggered_by',
        'triggered_by_user_id',
        'started_at',
        'completed_at',
        'result',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'cycle_number' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The action this cycle belongs to.
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(ScheduledAction::class, 'action_id');
    }

    /**
     * The user who triggered this cycle (if manual).
     */
    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    /**
     * Delivery attempts in this cycle.
     */
    public function deliveryAttempts(): HasMany
    {
        return $this->hasMany(DeliveryAttempt::class, 'execution_cycle_id');
    }

    /**
     * Check if this is a manual retry cycle.
     */
    public function isManual(): bool
    {
        return $this->triggered_by === self::TRIGGERED_MANUAL;
    }

    /**
     * Check if this cycle is still in progress.
     */
    public function isInProgress(): bool
    {
        return $this->result === self::RESULT_IN_PROGRESS;
    }

    /**
     * Mark cycle as successful.
     */
    public function markAsSuccess(): void
    {
        $this->update([
            'result' => self::RESULT_SUCCESS,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark cycle as failed.
     */
    public function markAsFailed(string $reason): void
    {
        $this->update([
            'result' => self::RESULT_FAILED,
            'completed_at' => now(),
            'failure_reason' => $reason,
        ]);
    }
}
