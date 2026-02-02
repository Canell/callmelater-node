<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $action_id
 * @property string $event_type
 * @property int $attempt_number
 * @property string $status
 * @property int|null $response_code
 * @property string|null $error_message
 * @property int|null $duration_ms
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CallbackAttempt extends Model
{
    use HasUuids;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'action_id',
        'event_type',
        'attempt_number',
        'status',
        'response_code',
        'error_message',
        'duration_ms',
    ];

    public function action(): BelongsTo
    {
        return $this->belongsTo(ScheduledAction::class, 'action_id');
    }
}
