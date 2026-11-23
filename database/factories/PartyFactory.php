<?php

namespace Database\Factories;

use App\Enums\PartyType;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Party>
 */
class PartyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'PTY-'.fake()->unique()->numerify('####'),
            'name' => fake()->unique()->company(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'note' => null,
            'type' => fake()->randomElement([PartyType::CUSTOMER, PartyType::SUPPLIER, PartyType::BOTH]),
            'is_active' => true,
        ];
    }

    /**
     * Indicate the party is a customer.
     */
    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PartyType::CUSTOMER,
        ]);
    }

    /**
     * Indicate the party is a supplier.
     */
    public function supplier(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PartyType::SUPPLIER,
        ]);
    }
}
