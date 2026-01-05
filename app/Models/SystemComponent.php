<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemComponent extends Model
{
    use HasFactory;

    public const STATUS_OPERATIONAL = 'operational';
    public const STATUS_DEGRADED = 'degraded';
    public const STATUS_OUTAGE = 'outage';

    public const STATUSES = [
        self::STATUS_OPERATIONAL,
        self::STATUS_DEGRADED,
        self::STATUS_OUTAGE,
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'current_status',
        'display_order',
        'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Get the status events for this component.
     *
     * @return HasMany<StatusEvent, $this>
     */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(StatusEvent::class, 'component_id');
    }

    /**
     * Get incidents affecting this component.
     *
     * @return BelongsToMany<Incident, $this>
     */
    public function incidents(): BelongsToMany
    {
        return $this->belongsToMany(Incident::class, 'incident_component', 'component_id', 'incident_id');
    }

    /**
     * Get the latest status event.
     *
     * @return HasMany<StatusEvent, $this>
     */
    public function latestStatusEvent(): HasMany
    {
        return $this->hasMany(StatusEvent::class, 'component_id')->latest()->limit(1);
    }

    /**
     * Scope to only visible components.
     */
    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    /**
     * Scope to order by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    /**
     * Check if component is operational.
     */
    public function isOperational(): bool
    {
        return $this->current_status === self::STATUS_OPERATIONAL;
    }

    /**
     * Check if component has issues.
     */
    public function hasIssues(): bool
    {
        return in_array($this->current_status, [self::STATUS_DEGRADED, self::STATUS_OUTAGE]);
    }

    /**
     * Get the status label for display.
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            self::STATUS_OPERATIONAL => 'Operational',
            self::STATUS_DEGRADED => 'Degraded Performance',
            self::STATUS_OUTAGE => 'Outage',
        ];

        return $labels[$this->current_status];
    }
}
