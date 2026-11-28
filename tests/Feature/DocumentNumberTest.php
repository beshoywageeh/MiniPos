<?php

use App\Enums\ResetPeriod;
use App\Models\DocumentSequence;
use App\Models\Sale;
use App\Support\DocumentNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a number uses the row prefix and padding and increments the sequence', function () {
    $sequence = DocumentSequence::factory()->forDocumentType('invoice', 'INV')->create([
        'next_number' => 7,
        'padding' => 4,
        'reset_period' => ResetPeriod::NEVER,
    ]);

    expect(DocumentNumber::next('invoice'))->toBe('INV-0007')
        ->and($sequence->refresh()->next_number)->toBe(8);
});

test('consecutive calls return consecutive numbers', function () {
    DocumentSequence::factory()->forDocumentType('sale', 'SALE')->create([
        'next_number' => 1,
        'padding' => 6,
        'reset_period' => ResetPeriod::NEVER,
    ]);

    expect(DocumentNumber::next('sale'))->toBe('SALE-000001')
        ->and(DocumentNumber::next('sale'))->toBe('SALE-000002')
        ->and(DocumentNumber::next('sale'))->toBe('SALE-000003');
});

test('a sequence resets when its period has rolled over', function (ResetPeriod $period, string $lastResetAt) {
    $this->travelTo('2026-11-20 10:00:00');

    $sequence = DocumentSequence::factory()->forDocumentType('sale', 'SALE')->create([
        'next_number' => 42,
        'padding' => 6,
        'reset_period' => $period,
        'last_reset_at' => $lastResetAt,
    ]);

    expect(DocumentNumber::next('sale'))->toBe('SALE-000001')
        ->and($sequence->refresh()->next_number)->toBe(2)
        ->and($sequence->last_reset_at->toDateString())->toBe('2026-11-20');
})->with([
    'daily' => [ResetPeriod::DAILY, '2026-11-19 10:00:00'],
    'weekly' => [ResetPeriod::WEEKLY, '2026-11-09 10:00:00'],
    'monthly' => [ResetPeriod::MONTHLY, '2026-10-20 10:00:00'],
    'yearly' => [ResetPeriod::YEARLY, '2025-11-20 10:00:00'],
]);

test('a sequence does not reset inside its period', function () {
    $this->travelTo('2026-11-20 10:00:00');

    DocumentSequence::factory()->forDocumentType('sale', 'SALE')->create([
        'next_number' => 7,
        'padding' => 6,
        'reset_period' => ResetPeriod::DAILY,
        'last_reset_at' => now()->startOfDay(),
    ]);

    expect(DocumentNumber::next('sale'))->toBe('SALE-000007');
});

test('a never sequence never resets', function () {
    $this->travelTo('2026-11-20 10:00:00');

    DocumentSequence::factory()->forDocumentType('sale', 'SALE')->create([
        'next_number' => 9,
        'padding' => 6,
        'reset_period' => ResetPeriod::NEVER,
        'last_reset_at' => now()->subYears(2),
    ]);

    expect(DocumentNumber::next('sale'))->toBe('SALE-000009');
});

test('the first call stamps last_reset_at without resetting a never sequence', function () {
    DocumentSequence::factory()->forDocumentType('sale', 'SALE')->create([
        'next_number' => 5,
        'padding' => 6,
        'reset_period' => ResetPeriod::NEVER,
        'last_reset_at' => null,
    ]);

    expect(DocumentNumber::next('sale'))->toBe('SALE-000005')
        ->and(DocumentSequence::query()->where('document_type', 'sale')->first()->last_reset_at)->not->toBeNull();
});

test('the first call stamps last_reset_at without resetting a periodic sequence', function () {
    $this->travelTo('2026-11-20 10:00:00');

    $sequence = DocumentSequence::factory()->forDocumentType('sale', 'SALE')->create([
        'next_number' => 26,
        'padding' => 6,
        'reset_period' => ResetPeriod::MONTHLY,
        'last_reset_at' => null,
    ]);

    expect(DocumentNumber::next('sale'))->toBe('SALE-000026')
        ->and($sequence->refresh()->next_number)->toBe(27)
        ->and($sequence->last_reset_at->toDateString())->toBe('2026-11-20');
});

test('a period reset skips document numbers that are already in use', function () {
    $this->travelTo('2026-11-20 10:00:00');

    Sale::factory()->create(['document_number' => 'SALE-000001']);
    Sale::factory()->create(['document_number' => 'SALE-000002']);

    $sequence = DocumentSequence::factory()->forDocumentType('sale', 'SALE')->create([
        'next_number' => 42,
        'padding' => 6,
        'reset_period' => ResetPeriod::MONTHLY,
        'last_reset_at' => '2026-10-20 10:00:00',
    ]);

    expect(DocumentNumber::next('sale'))->toBe('SALE-000003')
        ->and($sequence->refresh()->next_number)->toBe(4);
});

test('a rewound sequence skips document numbers that are already in use', function () {
    Sale::factory()->create(['document_number' => 'SALE-000001']);

    $sequence = DocumentSequence::factory()->forDocumentType('sale', 'SALE')->create([
        'next_number' => 1,
        'padding' => 6,
        'reset_period' => ResetPeriod::NEVER,
    ]);

    expect(DocumentNumber::next('sale'))->toBe('SALE-000002')
        ->and($sequence->refresh()->next_number)->toBe(3);
});

test('an unknown document type throws', function () {
    expect(fn () => DocumentNumber::next('missing'))
        ->toThrow(RuntimeException::class, 'No document sequence configured for [missing].');
});
