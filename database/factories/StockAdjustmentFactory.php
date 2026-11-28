<?php

namespace Database\Factories;

use App\Enums\MovementType;
use App\Enums\StockAdjustmentReason;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustment>
 */
class StockAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_number' => fake()->unique()->numerify('STADJ-######'),
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'movement_type' => MovementType::IN,
            'quantity' => 1,
            'unit_cost' => 0,
            'reason' => StockAdjustmentReason::CountCorrection,
            'note' => null,
            'is_override' => false,
            'movement_date' => today()->toDateString(),
            'created_by' => User::factory(),
        ];
    }
}
