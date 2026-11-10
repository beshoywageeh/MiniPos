<?php

namespace Database\Factories;

use App\Enums\ResetPeriod;
use App\Models\DocumentSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentSequence>
 */
class DocumentSequenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $documentType = fake()->unique()->word();

        return [
            'document_type' => $documentType,
            'prefix' => strtoupper(substr($documentType, 0, 3)),
            'next_number' => 1,
            'padding' => 6,
            'reset_period' => fake()->randomElement(ResetPeriod::cases()),
            'last_reset_at' => null,
        ];
    }

    /**
     * Indicate the sequence is for the given document type with a matching prefix.
     */
    public function forDocumentType(string $documentType, string $prefix): static
    {
        return $this->state(fn (array $attributes) => [
            'document_type' => $documentType,
            'prefix' => $prefix,
        ]);
    }
}
