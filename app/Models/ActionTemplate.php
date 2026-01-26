<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ActionTemplate extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'account_id',
        'created_by_user_id',
        'name',
        'description',
        'trigger_token',
        'mode',
        'timezone',
        'request_config',
        'gate_config',
        'max_attempts',
        'retry_strategy',
        'coordination_config',
        'default_coordination_keys',
        'placeholders',
        'is_active',
        'trigger_count',
        'last_triggered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_config' => 'array',
            'gate_config' => 'array',
            'coordination_config' => 'array',
            'default_coordination_keys' => 'array',
            'placeholders' => 'array',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ActionTemplate $template) {
            if (empty($template->trigger_token)) {
                $template->trigger_token = self::generateTriggerToken();
            }
        });
    }

    /**
     * Generate a unique trigger token.
     */
    public static function generateTriggerToken(): string
    {
        return 'clmt_'.Str::random(48);
    }

    // ==================== Relationships ====================

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ScheduledAction::class, 'template_id');
    }

    // ==================== Accessors ====================

    /**
     * Get the full trigger URL.
     */
    public function getTriggerUrlAttribute(): string
    {
        return url("/t/{$this->trigger_token}");
    }

    // ==================== Placeholder Helpers ====================

    /**
     * Get list of required placeholder names.
     *
     * @return array<string>
     */
    public function getRequiredPlaceholders(): array
    {
        /** @var array<array{name: string, required?: bool}> $placeholders */
        $placeholders = $this->placeholders ?? [];

        return collect($placeholders)
            ->filter(fn ($p) => $p['required'] ?? false)
            ->pluck('name')
            ->toArray();
    }

    /**
     * Get list of optional placeholder names.
     *
     * @return array<string>
     */
    public function getOptionalPlaceholders(): array
    {
        /** @var array<array{name: string, required?: bool}> $placeholders */
        $placeholders = $this->placeholders ?? [];

        return collect($placeholders)
            ->filter(fn ($p) => ! ($p['required'] ?? false))
            ->pluck('name')
            ->toArray();
    }

    /**
     * Get placeholder defaults.
     *
     * @return array<string, mixed>
     */
    public function getPlaceholderDefaults(): array
    {
        /** @var array<array{name: string, default?: string}> $placeholders */
        $placeholders = $this->placeholders ?? [];

        return collect($placeholders)
            ->filter(fn ($p) => array_key_exists('default', $p))
            ->pluck('default', 'name')
            ->toArray();
    }

    /**
     * Check if template is for immediate mode.
     */
    public function isImmediate(): bool
    {
        return $this->mode === ScheduledAction::MODE_IMMEDIATE;
    }

    /**
     * Check if template is for gated mode.
     */
    public function isGated(): bool
    {
        return $this->mode === ScheduledAction::MODE_GATED;
    }

    /**
     * Regenerate the trigger token.
     */
    public function regenerateToken(): void
    {
        $this->update([
            'trigger_token' => self::generateTriggerToken(),
        ]);
    }

    /**
     * Record a trigger.
     */
    public function recordTrigger(): void
    {
        $this->update([
            'trigger_count' => $this->trigger_count + 1,
            'last_triggered_at' => now(),
        ]);
    }
}
