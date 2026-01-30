<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ActionTemplate
 */
class TemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type ?? 'action',
            'mode' => $this->mode,
            'timezone' => $this->timezone,

            // Trigger info
            'trigger_url' => $this->trigger_url,
            'trigger_token' => $this->trigger_token,

            // Configuration (action templates)
            'request_config' => $this->when(! empty($this->request_config), $this->request_config),
            'gate_config' => $this->when(! empty($this->gate_config), $this->gate_config),

            // Configuration (chain templates)
            'chain_steps' => $this->when(! empty($this->chain_steps), $this->chain_steps),
            'chain_error_handling' => $this->when($this->type === 'chain', $this->chain_error_handling),

            // Retry settings
            'max_attempts' => $this->max_attempts,
            'retry_strategy' => $this->retry_strategy,

            // Coordination
            'coordination_config' => $this->when(! empty($this->coordination_config), $this->coordination_config),
            'default_coordination_keys' => $this->default_coordination_keys,

            // Placeholders
            'placeholders' => $this->placeholders ?? [],

            // Status & stats
            'is_active' => $this->is_active,
            'trigger_count' => $this->trigger_count,
            'last_triggered_at' => $this->resource->last_triggered_at?->toIso8601String(),

            // Timestamps
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
