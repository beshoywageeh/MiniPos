<?php

namespace Database\Factories;

use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 1, 20);
        $unitPrice = fake()->randomFloat(2, 1, 200);
        $costPrice = round($unitPrice * fake()->randomFloat(2, 0.6, 0.9), 2);
        $discountAmount = fake()->randomFloat(2, 0, 5);
        $taxAmount = fake()->randomFloat(2, 0, 10);

        return [
            'sale_id' => SaleFactory::new(),
            'product_id' => ProductFactory::new(),
            'product_name' => fake()->words(2, true),
            'sku' => 'SKU-'.fake()->numerify('#####'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'cost_price' => $costPrice,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'line_total' => round($quantity * $unitPrice - $discountAmount + $taxAmount, 2),
        ];
    }
}
