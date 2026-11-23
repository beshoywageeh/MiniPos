<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Purchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 5000);
        $discountAmount = fake()->randomFloat(2, 0, 20);
        $taxAmount = round($subtotal * 0.1, 2);
        $totalAmount = round($subtotal - $discountAmount + $taxAmount, 2);
        $fullyPaid = fake()->boolean(70);
        $paidAmount = $fullyPaid ? $totalAmount : round($totalAmount * 0.4, 2);

        return [
            'document_number' => 'PUR-'.fake()->unique()->numerify('######'),
            'party_id' => PartyFactory::new()->supplier(),
            'warehouse_id' => WarehouseFactory::new(),
            'invoice_date' => fake()->dateTimeBetween('-30 days'),
            'due_date' => fake()->dateTimeBetween('+1 week', '+30 days'),
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => round($totalAmount - $paidAmount, 2),
            'status' => $fullyPaid ? DocumentStatus::PAID : DocumentStatus::PARTIAL,
            'notes' => null,
            'created_by' => UserFactory::new(),
        ];
    }
}
