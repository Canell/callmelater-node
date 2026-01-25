<?php

namespace Database\Factories;

use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamMember>
 */
class TeamMemberFactory extends Factory
{
    protected $model = TeamMember::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
        ];
    }

    /**
     * Indicate that the team member only has a phone.
     */
    public function withPhone(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
            'phone' => '+1' . fake()->numerify('##########'),
        ]);
    }

    /**
     * Indicate that the team member has both email and phone.
     */
    public function withBoth(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+1' . fake()->numerify('##########'),
        ]);
    }
}
