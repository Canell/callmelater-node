<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAttempt extends Model
{
    use HasUuids;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    // Failure categories for health monitoring
    public const CATEGORY_SUCCESS = 'success';

    public const CATEGORY_CUSTOMER_4XX = 'customer_4xx';

    public const CATEGORY_CUSTOMER_5XX = 'customer_5xx';

    public const CATEGORY_DELIVERY_ERROR = 'delivery_error';

    protected $fillable = [
        'action_id',
        'execution_cycle_id',
        'attempt_number',
        'status',
        'failure_category',
        'target_domain',
        'response_code',
        'response_body',
        'error_message',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'response_code' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(ScheduledAction::class, 'action_id');
    }

    public function executionCycle(): BelongsTo
    {
        return $this->belongsTo(ExecutionCycle::class, 'execution_cycle_id');
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }
}
