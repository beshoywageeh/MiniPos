<?php

namespace Database\Factories;

use App\Models\PurchaseReturnItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseReturnItem>
 */
class PurchaseReturnItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 10);
        $unitCost = fake()->randomFloat(2, 1, 200);

        return [
            'purchase_return_id' => PurchaseReturnFactory::new(),
            'product_id' => ProductFactory::new(),
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'line_total' => round($quantity * $unitCost, 2),
        ];
    }
}
