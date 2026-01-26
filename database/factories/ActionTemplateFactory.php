<?php

namespace Database\Factories;

use App\Models\ActionTemplate;
use App\Models\ScheduledAction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ActionTemplate>
 */
class ActionTemplateFactory extends Factory
{
    protected $model = ActionTemplate::class;

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
            'description' => fake()->optional()->sentence(),
            'trigger_token' => 'clmt_'.Str::random(48),
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'timezone' => 'UTC',
            'request_config' => [
                'url' => 'https://api.example.com/webhook',
                'method' => 'POST',
            ],
            'gate_config' => null,
            'max_attempts' => 5,
            'retry_strategy' => 'exponential',
            'coordination_config' => null,
            'default_coordination_keys' => null,
            'placeholders' => null,
            'is_active' => true,
            'trigger_count' => 0,
            'last_triggered_at' => null,
        ];
    }

    /**
     * Configure as a gated template.
     */
    public function gated(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => ScheduledAction::MODE_GATED,
            'request_config' => null,
            'gate_config' => [
                'message' => fake()->sentence(),
                'recipients' => [fake()->email()],
                'timeout' => '4h',
                'on_timeout' => 'expire',
                'confirmation_mode' => 'first_response',
                'max_snoozes' => 5,
            ],
        ]);
    }

    /**
     * Configure as a gated template with HTTP request on approval.
     */
    public function gatedWithRequest(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => ScheduledAction::MODE_GATED,
            'request_config' => [
                'url' => 'https://api.example.com/webhook',
                'method' => 'POST',
            ],
            'gate_config' => [
                'message' => fake()->sentence(),
                'recipients' => [fake()->email()],
                'timeout' => '4h',
            ],
        ]);
    }

    /**
     * Configure with placeholders.
     */
    public function withPlaceholders(array $placeholders = null): static
    {
        return $this->state(fn (array $attributes) => [
            'placeholders' => $placeholders ?? [
                ['name' => 'service', 'required' => true],
                ['name' => 'version', 'required' => false, 'default' => '1.0'],
            ],
        ]);
    }

    /**
     * Configure as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
