<?php

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_number' => 'EXP-'.fake()->unique()->numerify('######'),
            'expense_category' => fake()->randomElement(ExpenseCategory::cases()),
            'cashbox_id' => CashboxFactory::new(),
            'amount' => fake()->randomFloat(2, 5, 500),
            'expense_date' => fake()->dateTimeBetween('-30 days'),
            'description' => fake()->sentence(),
            'notes' => null,
            'created_by' => UserFactory::new(),
        ];
    }

    /**
     * Indicate the expense belongs to a specific category.
     */
    public function category(ExpenseCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'expense_category' => $category,
        ]);
    }
}
