<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\SalesReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesReturn>
 */
class SalesReturnFactory extends Factory
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

        return [
            'document_number' => 'SRT-'.fake()->unique()->numerify('######'),
            'sale_id' => SaleFactory::new(),
            'party_id' => PartyFactory::new()->customer(),
            'warehouse_id' => WarehouseFactory::new(),
            'return_date' => fake()->dateTimeBetween('-30 days'),
            'total_amount' => round($quantity * $unitPrice, 2),
            'status' => fake()->randomElement([DocumentStatus::DRAFT, DocumentStatus::APPROVED]),
            'notes' => null,
            'created_by' => UserFactory::new(),
        ];
    }
}
