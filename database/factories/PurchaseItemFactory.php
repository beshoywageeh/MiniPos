<?php

namespace Database\Factories;

use App\Models\PurchaseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseItem>
 */
class PurchaseItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 50);
        $unitPrice = fake()->randomFloat(2, 1, 200);
        $discountAmount = fake()->randomFloat(2, 0, 10);
        $taxAmount = fake()->randomFloat(2, 0, 15);

        return [
            'purchase_id' => PurchaseFactory::new(),
            'product_id' => ProductFactory::new(),
            'product_name' => fake()->words(2, true),
            'sku' => 'SKU-'.fake()->numerify('#####'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'line_total' => round($quantity * $unitPrice - $discountAmount + $taxAmount, 2),
        ];
    }
}
