<?php

namespace Database\Factories;

use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => ProductFactory::new(),
            'warehouse_id' => WarehouseFactory::new(),
            'movement_type' => fake()->randomElement(['in', 'out']),
            'quantity' => fake()->randomFloat(3, 1, 100),
            'unit_cost' => fake()->randomFloat(2, 1, 200),
            'reference_type' => null,
            'reference_id' => null,
            'note' => null,
            'movement_date' => fake()->dateTimeBetween('-30 days'),
            'created_by' => UserFactory::new(),
        ];
    }
}
