<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
