<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $action_id
 * @property string $email
 * @property string $status
 * @property string|null $response_token
 * @property Carbon|null $responded_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ReminderRecipient extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_SNOOZED = 'snoozed';

    protected $fillable = [
        'action_id',
        'email',
        'status',
        'response_token',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(ScheduledAction::class, 'action_id');
    }

    public function hasResponded(): bool
    {
        return $this->status !== self::STATUS_PENDING;
    }
}
