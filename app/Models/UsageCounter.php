<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $account_id
 * @property int $period_year
 * @property int $period_month
 * @property int $actions_created
 * @property int $sms_sent
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Account $account
 */
class UsageCounter extends Model
{
    protected $fillable = [
        'account_id',
        'period_year',
        'period_month',
        'actions_created',
        'sms_sent',
    ];

    protected $attributes = [
        'actions_created' => 0,
        'sms_sent' => 0,
    ];

    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'actions_created' => 'integer',
            'sms_sent' => 'integer',
        ];
    }

    /**
     * The account this counter belongs to.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get or create a counter for the current month.
     */
    public static function forCurrentMonth(string $accountId): self
    {
        return self::firstOrCreate([
            'account_id' => $accountId,
            'period_year' => now()->year,
            'period_month' => now()->month,
        ]);
    }

    /**
     * Atomically increment actions count.
     */
    public function incrementActions(int $count = 1): void
    {
        $this->increment('actions_created', $count);
    }

    /**
     * Atomically increment SMS count.
     */
    public function incrementSms(int $count = 1): void
    {
        $this->increment('sms_sent', $count);
    }

    /**
     * Get remaining actions quota.
     */
    public function getRemainingActions(int $limit): int
    {
        return max(0, $limit - $this->actions_created);
    }

    /**
     * Get remaining SMS quota.
     */
    public function getRemainingSms(int $limit): int
    {
        return max(0, $limit - $this->sms_sent);
    }
}
