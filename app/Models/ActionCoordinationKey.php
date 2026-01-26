<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $action_id
 * @property string $coordination_key
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read ScheduledAction $action
 */
class ActionCoordinationKey extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'action_id',
        'coordination_key',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(ScheduledAction::class, 'action_id');
    }
}
