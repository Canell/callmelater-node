<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'component_id',
        'incident_id',
        'status',
        'message',
        'created_by',
    ];

    /**
     * Get the component this event belongs to.
     *
     * @return BelongsTo<SystemComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(SystemComponent::class, 'component_id');
    }

    /**
     * Get the incident this event is part of (if any).
     *
     * @return BelongsTo<Incident, $this>
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /**
     * Get the user who created this event.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the status label for display.
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            SystemComponent::STATUS_OPERATIONAL => 'Operational',
            SystemComponent::STATUS_DEGRADED => 'Degraded Performance',
            SystemComponent::STATUS_OUTAGE => 'Outage',
        ];

        return $labels[$this->status];
    }
}
