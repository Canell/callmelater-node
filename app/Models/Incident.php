<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incident extends Model
{
    use HasFactory;

    public const IMPACT_MINOR = 'minor';

    public const IMPACT_MAJOR = 'major';

    public const IMPACT_CRITICAL = 'critical';

    public const IMPACTS = [
        self::IMPACT_MINOR,
        self::IMPACT_MAJOR,
        self::IMPACT_CRITICAL,
    ];

    public const STATUS_INVESTIGATING = 'investigating';

    public const STATUS_IDENTIFIED = 'identified';

    public const STATUS_MONITORING = 'monitoring';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUSES = [
        self::STATUS_INVESTIGATING,
        self::STATUS_IDENTIFIED,
        self::STATUS_MONITORING,
        self::STATUS_RESOLVED,
    ];

    protected $fillable = [
        'title',
        'impact',
        'status',
        'summary',
        'started_at',
        'resolved_at',
        'created_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the components affected by this incident.
     *
     * @return BelongsToMany<SystemComponent, $this>
     */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(SystemComponent::class, 'incident_component', 'incident_id', 'component_id');
    }

    /**
     * Get the status events for this incident.
     */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(StatusEvent::class);
    }

    /**
     * Get the user who created this incident.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to active (unresolved) incidents.
     */
    public function scopeActive($query)
    {
        return $query->where('status', '!=', self::STATUS_RESOLVED);
    }

    /**
     * Scope to resolved incidents.
     */
    public function scopeResolved($query)
    {
        return $query->where('status', self::STATUS_RESOLVED);
    }

    /**
     * Scope to recent incidents (last N days).
     */
    public function scopeRecent($query, int $days = 90)
    {
        return $query->where('started_at', '>=', now()->subDays($days));
    }

    /**
     * Check if incident is resolved.
     */
    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    /**
     * Check if incident is active.
     */
    public function isActive(): bool
    {
        return ! $this->isResolved();
    }

    /**
     * Get the impact label for display.
     */
    public function getImpactLabelAttribute(): string
    {
        $labels = [
            self::IMPACT_MINOR => 'Minor',
            self::IMPACT_MAJOR => 'Major',
            self::IMPACT_CRITICAL => 'Critical',
        ];

        return $labels[$this->impact];
    }

    /**
     * Get the status label for display.
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            self::STATUS_INVESTIGATING => 'Investigating',
            self::STATUS_IDENTIFIED => 'Identified',
            self::STATUS_MONITORING => 'Monitoring',
            self::STATUS_RESOLVED => 'Resolved',
        ];

        return $labels[$this->status];
    }

    /**
     * Get the duration of the incident.
     */
    public function getDurationAttribute(): ?string
    {
        $end = $this->resolved_at ?? now();
        $diff = $this->started_at->diff($end);

        if ($diff->days > 0) {
            return $diff->days.'d '.$diff->h.'h';
        }
        if ($diff->h > 0) {
            return $diff->h.'h '.$diff->i.'m';
        }

        return $diff->i.'m';
    }
}
