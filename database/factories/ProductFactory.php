<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $purchasePrice = fake()->randomFloat(2, 1, 500);

        return [
            'code' => 'PRD-'.fake()->unique()->numerify('#####'),
            'sku' => 'SKU-'.fake()->unique()->numerify('#####'),
            'barcode' => fake()->unique()->ean13(),
            'name' => fake()->unique()->words(2, true),
            'type' => ProductType::Product,
            'description' => fake()->sentence(),
            'category_id' => CategoryFactory::new(),
            'brand_id' => BrandFactory::new(),
            'unit_id' => UnitFactory::new(),
            'purchase_price' => $purchasePrice,
            'selling_price' => round($purchasePrice * fake()->randomFloat(2, 1.15, 1.6), 2),
            'min_stock' => fake()->randomFloat(2, 1, 20),
            'is_active' => true,
        ];
    }

    public function service(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProductType::Service,
        ]);
    }
}
