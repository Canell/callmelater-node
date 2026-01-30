<?php

namespace Database\Factories;

use App\Models\ActionChain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ActionChain>
 */
class ActionChainFactory extends Factory
{
    protected $model = ActionChain::class;

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
            'steps' => [
                [
                    'name' => 'Step 1',
                    'type' => ActionChain::STEP_HTTP_CALL,
                    'url' => 'https://api.example.com/step1',
                    'method' => 'POST',
                ],
                [
                    'name' => 'Step 2',
                    'type' => ActionChain::STEP_DELAY,
                    'delay' => '5m',
                ],
            ],
            'input' => null,
            'context' => null,
            'status' => ActionChain::STATUS_PENDING,
            'current_step' => 0,
            'error_handling' => ActionChain::ERROR_FAIL_CHAIN,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Configure as running.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ActionChain::STATUS_RUNNING,
            'started_at' => now(),
        ]);
    }

    /**
     * Configure as completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ActionChain::STATUS_COMPLETED,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
        ]);
    }

    /**
     * Configure as failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ActionChain::STATUS_FAILED,
            'started_at' => now()->subMinutes(5),
        ]);
    }

    /**
     * Configure as cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ActionChain::STATUS_CANCELLED,
        ]);
    }

    /**
     * Configure with approval step.
     */
    public function withApprovalStep(): static
    {
        return $this->state(fn (array $attributes) => [
            'steps' => [
                [
                    'name' => 'Initial Request',
                    'type' => ActionChain::STEP_HTTP_CALL,
                    'url' => 'https://api.example.com/request',
                    'method' => 'POST',
                ],
                [
                    'name' => 'Manager Approval',
                    'type' => ActionChain::STEP_GATED,
                    'gate' => [
                        'message' => 'Please approve this request',
                        'recipients' => ['manager@example.com'],
                        'channels' => ['email'],
                    ],
                ],
                [
                    'name' => 'Execute',
                    'type' => ActionChain::STEP_HTTP_CALL,
                    'url' => 'https://api.example.com/execute',
                    'method' => 'POST',
                ],
            ],
        ]);
    }

    /**
     * Configure with input variables.
     */
    public function withInput(array $input): static
    {
        return $this->state(fn (array $attributes) => [
            'input' => $input,
        ]);
    }

    /**
     * Configure with skip_step error handling.
     */
    public function skipOnError(): static
    {
        return $this->state(fn (array $attributes) => [
            'error_handling' => ActionChain::ERROR_SKIP_STEP,
        ]);
    }
}
