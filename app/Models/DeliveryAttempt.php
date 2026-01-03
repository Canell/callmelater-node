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

    protected $fillable = [
        'action_id',
        'attempt_number',
        'status',
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

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }
}
