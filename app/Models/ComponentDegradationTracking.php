<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComponentDegradationTracking extends Model
{
    use HasFactory;

    protected $table = 'component_degradation_tracking';

    protected $fillable = [
        'component_id',
        'degraded_since',
        'reminder_action_id',
        'notified_at',
    ];

    protected $casts = [
        'degraded_since' => 'datetime',
        'notified_at' => 'datetime',
    ];

    public function component(): BelongsTo
    {
        return $this->belongsTo(SystemComponent::class, 'component_id');
    }

    public function reminderAction(): BelongsTo
    {
        return $this->belongsTo(ScheduledAction::class, 'reminder_action_id');
    }

    /**
     * Get how long the component has been degraded.
     */
    public function getDegradedDurationAttribute(): string
    {
        return $this->degraded_since->diffForHumans(now(), ['parts' => 2]);
    }

    /**
     * Check if enough time has passed to send a reminder.
     */
    public function shouldSendReminder(int $delayMinutes = 15): bool
    {
        // Already notified
        if ($this->notified_at !== null) {
            return false;
        }

        // Check if degraded long enough
        return $this->degraded_since->addMinutes($delayMinutes)->isPast();
    }
}
