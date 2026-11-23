<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_number' => 'PAY-'.fake()->unique()->numerify('######'),
            'party_id' => PartyFactory::new(),
            'cashbox_id' => CashboxFactory::new(),
            'sale_id' => null,
            'purchase_id' => null,
            'expense_id' => null,
            'type' => fake()->randomElement([PaymentType::recipt, PaymentType::payment]),
            'method' => fake()->randomElement(PaymentMethod::cases()),
            'amount' => fake()->randomFloat(2, 5, 1000),
            'payment_date' => fake()->dateTimeBetween('-30 days'),
            'reference_number' => fake()->optional(0.6)->numerify('REF-######'),
            'notes' => null,
            'created_by' => UserFactory::new(),
        ];
    }

    /**
     * Indicate the payment settles a specific sale.
     */
    public function forSale(Sale $sale): static
    {
        return $this->state(fn (array $attributes) => [
            'party_id' => $sale->party_id,
            'sale_id' => $sale->id,
            'type' => PaymentType::recipt,
            'amount' => (float) $sale->total_amount,
        ]);
    }

    /**
     * Indicate the payment settles a specific purchase.
     */
    public function forPurchase(Purchase $purchase): static
    {
        return $this->state(fn (array $attributes) => [
            'party_id' => $purchase->party_id,
            'purchase_id' => $purchase->id,
            'type' => PaymentType::payment,
            'amount' => (float) $purchase->total_amount,
        ]);
    }
}
