<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderEvent extends Model
{
    use HasUuids;

    public const TYPE_SENT = 'sent';

    public const TYPE_SNOOZED = 'snoozed';

    public const TYPE_CONFIRMED = 'confirmed';

    public const TYPE_DECLINED = 'declined';

    public const TYPE_ESCALATED = 'escalated';

    public const TYPE_EXPIRED = 'expired';

    protected $fillable = [
        'reminder_id',
        'event_type',
        'actor_email',
        'captured_timezone',
        'notes',
    ];

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(ScheduledAction::class, 'reminder_id');
    }
}
