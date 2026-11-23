<?php

namespace Database\Factories;

use App\Models\SalesReturnItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesReturnItem>
 */
class SalesReturnItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 1, 5);
        $unitPrice = fake()->randomFloat(2, 1, 200);
        $costPrice = round($unitPrice * fake()->randomFloat(2, 0.6, 0.9), 2);

        return [
            'sales_return_id' => SalesReturnFactory::new(),
            'product_id' => ProductFactory::new(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'cost_price' => $costPrice,
            'line_total' => round($quantity * $unitPrice, 2),
        ];
    }
}
