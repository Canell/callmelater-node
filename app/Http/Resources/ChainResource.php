<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\TerminologyMapping;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ActionChain
 */
class ChainResource extends JsonResource
{
    use TerminologyMapping;
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'current_step' => $this->current_step,
            'total_steps' => $this->getTotalSteps(),
            'error_handling' => $this->error_handling,
            'failure_reason' => $this->failure_reason,
            'input' => $this->input,
            'steps' => $this->formatSteps(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    /**
     * Format steps with their execution status.
     *
     * @return array<int, array<string, mixed>>
     */
    private function formatSteps(): array
    {
        $steps = $this->steps;
        $context = $this->context ?? [];

        return array_map(function ($step, $index) use ($context) {
            $stepContext = $context['steps'][$index] ?? null;
            $internalType = $step['type'];

            return [
                'index' => $index,
                'name' => $step['name'] ?? "Step {$index}",
                // New terminology (primary)
                'step_type' => $this->mapStepType($internalType),
                // Legacy terminology (for backwards compatibility)
                'type' => $internalType,
                'status' => $this->getStepStatus($index, $stepContext),
                'response' => $stepContext['response'] ?? null,
                'completed_at' => $stepContext['completed_at'] ?? null,
                // Include definition fields based on type
                ...$this->getStepDefinitionFields($step),
            ];
        }, $steps, array_keys($steps));
    }

    /**
     * Get the status for a step.
     *
     * @param  array<string, mixed>|null  $stepContext
     */
    private function getStepStatus(int $index, ?array $stepContext): string
    {
        if ($stepContext) {
            return $stepContext['status'] ?? 'completed';
        }

        if ($index < $this->current_step) {
            return 'completed';
        }

        if ($index === $this->current_step && $this->status === 'running') {
            return 'in_progress';
        }

        return 'pending';
    }

    /**
     * Get the definition fields for a step (excluding name and type).
     *
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>
     */
    private function getStepDefinitionFields(array $step): array
    {
        $type = $step['type'];

        return match ($type) {
            'http_call' => [
                'url' => $step['url'] ?? null,
                'method' => $step['method'] ?? 'POST',
            ],
            'gated' => [
                'gate' => [
                    'message' => $step['gate']['message'] ?? null,
                    'recipients_count' => count($step['gate']['recipients'] ?? []),
                    'channels' => $step['gate']['channels'] ?? [],
                ],
            ],
            'delay' => [
                'delay' => $step['delay'] ?? null,
                // Also include as 'wait' for new terminology
                'wait' => $step['delay'] ?? null,
            ],
            default => [],
        };
    }
}
