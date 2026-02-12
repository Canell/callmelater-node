<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registered webhook endpoint for receiving action events.
 *
 * @property string $id
 * @property string $account_id
 * @property int|null $created_by_user_id
 * @property string|null $name
 * @property string $url
 * @property array<string> $events
 * @property string|null $secret
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Webhook extends Model
{
    use HasFactory, HasUuids;

    // Supported event types
    public const EVENT_ACTION_EXECUTED = 'action.executed';

    public const EVENT_ACTION_FAILED = 'action.failed';

    public const EVENT_ACTION_EXPIRED = 'action.expired';

    public const EVENT_REMINDER_RESPONDED = 'reminder.responded';

    public const ALL_EVENTS = [
        self::EVENT_ACTION_EXECUTED,
        self::EVENT_ACTION_FAILED,
        self::EVENT_ACTION_EXPIRED,
        self::EVENT_REMINDER_RESPONDED,
    ];

    protected $fillable = [
        'account_id',
        'created_by_user_id',
        'name',
        'url',
        'events',
        'secret',
        'is_active',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Check if this webhook is subscribed to a specific event.
     */
    public function subscribedTo(string $event): bool
    {
        return in_array($event, $this->events, true);
    }

    /**
     * Get all active webhooks for an account that are subscribed to a specific event.
     *
     * @return Collection<int, Webhook>
     */
    public static function getForEvent(string $accountId, string $event): Collection
    {
        return static::where('account_id', $accountId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Webhook $webhook) => $webhook->subscribedTo($event));
    }
}
