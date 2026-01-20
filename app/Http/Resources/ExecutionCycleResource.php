<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ExecutionCycle
 */
class ExecutionCycleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cycle_number' => $this->cycle_number,
            'triggered_by' => $this->triggered_by,
            'triggered_by_user' => $this->when(
                $this->triggered_by === 'manual' && $this->relationLoaded('triggeredByUser') && $this->triggeredByUser,
                fn () => [
                    'id' => $this->triggeredByUser->id,
                    'name' => $this->triggeredByUser->name,
                    'email' => $this->triggeredByUser->email,
                ]
            ),
            'started_at' => $this->started_at->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'result' => $this->result,
            'failure_reason' => $this->when($this->result === 'failed', $this->failure_reason),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
