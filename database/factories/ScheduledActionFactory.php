<?php

namespace Database\Factories;

use App\Models\ScheduledAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScheduledAction>
 */
class ScheduledActionFactory extends Factory
{
    protected $model = ScheduledAction::class;

    /**
     * Define the model's default state.
     * Note: Tests must provide 'account_id' when using this factory.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // account_id must be provided by tests
            'name' => fake()->words(3, true),
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'resolution_status' => ScheduledAction::STATUS_PENDING_RESOLUTION,
            'intent_type' => ScheduledAction::INTENT_WALL_CLOCK,
            'intent_payload' => ['delay' => '1h'],
            'timezone' => 'UTC',
            'request' => [
                'url' => 'https://api.example.com/webhook',
                'method' => 'POST',
            ],
            'gate' => null,
            'max_attempts' => 5,
            'retry_strategy' => 'exponential',
            'attempt_count' => 0,
        ];
    }

    /**
     * Configure as resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'resolution_status' => ScheduledAction::STATUS_RESOLVED,
            'execute_at_utc' => now()->addHour(),
        ]);
    }

    /**
     * Configure as executed.
     */
    public function executed(): static
    {
        return $this->state(fn (array $attributes) => [
            'resolution_status' => ScheduledAction::STATUS_EXECUTED,
            'execute_at_utc' => now()->subMinutes(5),
        ]);
    }

    /**
     * Configure as cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'resolution_status' => ScheduledAction::STATUS_CANCELLED,
        ]);
    }

    /**
     * Configure as failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'resolution_status' => ScheduledAction::STATUS_FAILED,
        ]);
    }

    /**
     * Configure as gated mode.
     */
    public function gated(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => ScheduledAction::MODE_GATED,
            'request' => null,
            'gate' => [
                'message' => fake()->sentence(),
                'recipients' => [fake()->email()],
                'channels' => ['email'],
                'timeout' => '4h',
                'on_timeout' => 'expire',
            ],
        ]);
    }

    /**
     * Configure as part of a chain.
     */
    public function forChain(string $chainId, int $step = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'chain_id' => $chainId,
            'chain_step' => $step,
        ]);
    }

    /**
     * Configure with coordination keys.
     * Note: Coordination keys are stored in a related table, not directly on the model.
     * Use afterCreating to add keys via the relationship.
     */
    public function withCoordinationKeys(array $keys): static
    {
        return $this->afterCreating(function (ScheduledAction $action) use ($keys) {
            foreach ($keys as $key) {
                $action->coordinationKeyRecords()->create([
                    'coordination_key' => $key,
                ]);
            }
        });
    }
}
