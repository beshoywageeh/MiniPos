<?php

namespace Database\Factories;

use App\Models\Cashbox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cashbox>
 */
class CashboxFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'CASH-'.fake()->unique()->numerify('###'),
            'name' => fake()->unique()->city().' Cashbox',
            'description' => null,
            'opening_balance' => fake()->randomFloat(2, 0, 1000),
            'is_active' => true,
        ];
    }
}
