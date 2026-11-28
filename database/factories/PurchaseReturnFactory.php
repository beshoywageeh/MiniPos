<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\PurchaseReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseReturn>
 */
class PurchaseReturnFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_number' => 'PRT-'.fake()->unique()->numerify('######'),
            'purchase_id' => PurchaseFactory::new(),
            'party_id' => PartyFactory::new()->supplier(),
            'warehouse_id' => WarehouseFactory::new(),
            'return_date' => fake()->dateTimeBetween('-30 days'),
            'total_amount' => fake()->randomFloat(2, 10, 1000),
            'status' => fake()->randomElement([
                DocumentStatus::PENDING,
                DocumentStatus::PAID,
                DocumentStatus::PARTIAL,
            ]),
            'notes' => null,
            'created_by' => UserFactory::new(),
        ];
    }
}
