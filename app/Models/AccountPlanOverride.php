<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit log for manual plan overrides.
 *
 * Tracks when admins grant or revoke manual plan access to accounts.
 */
class AccountPlanOverride extends Model
{
    public const ACTION_SET = 'set';
    public const ACTION_REVOKED = 'revoked';
    public const ACTION_EXPIRED = 'expired';

    protected $fillable = [
        'account_id',
        'plan',
        'expires_at',
        'reason',
        'set_by_user_id',
        'action',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /**
     * The account this override was applied to.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * The admin user who set this override.
     */
    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by_user_id');
    }
}
