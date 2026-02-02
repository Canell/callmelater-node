<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

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
     * Indicate that the contact only has a phone.
     */
    public function withPhone(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
            'phone' => '+1' . fake()->numerify('##########'),
        ]);
    }

    /**
     * Indicate that the contact has both email and phone.
     */
    public function withBoth(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+1' . fake()->numerify('##########'),
        ]);
    }
}
