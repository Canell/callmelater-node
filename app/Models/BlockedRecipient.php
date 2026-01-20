<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $recipient
 * @property string|null $reason
 * @property string|null $blocked_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BlockedRecipient extends Model
{
    use HasUuids;

    protected $fillable = [
        'recipient',
        'reason',
        'blocked_by',
    ];

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    /**
     * Check if a recipient (email or phone) is blocked.
     */
    public static function isBlocked(string $recipient): bool
    {
        // Normalize the recipient for comparison
        $normalized = strtolower(trim($recipient));

        return self::query()
            ->whereRaw('LOWER(recipient) = ?', [$normalized])
            ->exists();
    }

    /**
     * Block a recipient.
     */
    public static function block(string $recipient, ?string $reason = null, ?string $blockedBy = null): self
    {
        return self::firstOrCreate(
            ['recipient' => strtolower(trim($recipient))],
            [
                'reason' => $reason,
                'blocked_by' => $blockedBy,
            ]
        );
    }
}
