<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $action_id
 * @property string|null $team_member_id
 * @property string $email
 * @property string $status
 * @property string|null $response_token
 * @property Carbon|null $responded_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ScheduledAction $action
 * @property-read TeamMember|null $teamMember
 * @property-read string $display_name
 */
class ReminderRecipient extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_SNOOZED = 'snoozed';

    protected $fillable = [
        'action_id',
        'team_member_id',
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

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    /**
     * Get the display name for this recipient.
     * Returns team member name if available, otherwise the email/phone.
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->teamMember?->full_name ?? $this->email
        );
    }

    public function hasResponded(): bool
    {
        return in_array($this->status, [
            self::STATUS_CONFIRMED,
            self::STATUS_DECLINED,
            self::STATUS_SNOOZED,
        ]);
    }
}
