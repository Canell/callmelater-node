<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property bool $health_alerts
 * @property bool $incident_alerts
 * @property array $channels
 * @property-read User $user
 */
class AdminNotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'health_alerts',
        'incident_alerts',
        'channels',
    ];

    protected $casts = [
        'health_alerts' => 'boolean',
        'incident_alerts' => 'boolean',
        'channels' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get admins who opted into health alerts.
     */
    public static function getHealthAlertRecipients(): array
    {
        return static::where('health_alerts', true)
            ->with('user')
            ->get()
            ->map(fn ($pref) => $pref->user->email)
            ->filter()
            ->toArray();
    }

    /**
     * Get admins who opted into incident alerts.
     */
    public static function getIncidentAlertRecipients(): array
    {
        return static::where('incident_alerts', true)
            ->with('user')
            ->get()
            ->map(fn ($pref) => $pref->user->email)
            ->filter()
            ->toArray();
    }
}
