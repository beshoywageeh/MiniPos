<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'UNT-'.fake()->unique()->numerify('####'),
            'name' => fake()->unique()->word(),
            'abbreviation' => fake()->lexify('???'),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate the unit is a base unit used for demo data.
     */
    public function piece(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'UNT-0001',
            'name' => 'Piece',
            'abbreviation' => 'pcs',
        ]);
    }
}
