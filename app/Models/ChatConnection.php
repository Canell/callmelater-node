<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $account_id
 * @property string $provider
 * @property string $name
 * @property string|null $teams_tenant_id
 * @property string|null $teams_webhook_url
 * @property string|null $slack_team_id
 * @property string|null $slack_bot_token
 * @property string|null $slack_signing_secret
 * @property string|null $slack_channel_id
 * @property string|null $slack_channel_name
 * @property bool $is_active
 * @property Carbon|null $connected_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Account $account
 *
 * @method static Builder|ChatConnection teams()
 * @method static Builder|ChatConnection slack()
 * @method static Builder|ChatConnection active()
 */
class ChatConnection extends Model
{
    use HasUuids;

    public const PROVIDER_TEAMS = 'teams';

    public const PROVIDER_SLACK = 'slack';

    protected $fillable = [
        'account_id',
        'provider',
        'name',
        'teams_tenant_id',
        'teams_webhook_url',
        'slack_team_id',
        'slack_bot_token',
        'slack_signing_secret',
        'slack_channel_id',
        'slack_channel_name',
        'is_active',
        'connected_at',
    ];

    protected function casts(): array
    {
        return [
            'slack_bot_token' => 'encrypted',
            'slack_signing_secret' => 'encrypted',
            'is_active' => 'boolean',
            'connected_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Scope to only Teams connections.
     */
    public function scopeTeams(Builder $query): Builder
    {
        return $query->where('provider', self::PROVIDER_TEAMS);
    }

    /**
     * Scope to only Slack connections.
     */
    public function scopeSlack(Builder $query): Builder
    {
        return $query->where('provider', self::PROVIDER_SLACK);
    }

    /**
     * Scope to only active connections.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if this is a Teams connection.
     */
    public function isTeams(): bool
    {
        return $this->provider === self::PROVIDER_TEAMS;
    }

    /**
     * Check if this is a Slack connection.
     */
    public function isSlack(): bool
    {
        return $this->provider === self::PROVIDER_SLACK;
    }
}
