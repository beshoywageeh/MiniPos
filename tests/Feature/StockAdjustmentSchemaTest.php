<?php

use App\Enums\ResetPeriod;
use App\Models\DocumentSequence;
use App\Models\StockAdjustment;
use App\Support\DocumentNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('stock adjustment schema and sequence are available', function () {
    expect(Schema::hasColumns('stock_adjustments', [
        'document_number', 'product_id', 'warehouse_id', 'movement_type', 'quantity',
        'unit_cost', 'reason', 'note', 'is_override', 'movement_date', 'created_by',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('stock_movements', ['balance_after', 'is_override']))->toBeTrue();

    $sequence = DocumentSequence::query()->where('document_type', 'stock_adjustment')->firstOrFail();

    expect($sequence->prefix)->toBe('STADJ')
        ->and($sequence->padding)->toBe(6)
        ->and($sequence->reset_period)->toBe(ResetPeriod::MONTHLY);
});

test('stock adjustment numbers skip numbers already used by adjustments', function () {
    $sequence = DocumentSequence::query()->where('document_type', 'stock_adjustment')->firstOrFail();
    $taken = DocumentNumber::preview($sequence);
    StockAdjustment::factory()->create(['document_number' => $taken]);

    expect(DocumentNumber::next('stock_adjustment'))->toBe('STADJ-000002')
        ->and($sequence->refresh()->next_number)->toBe(3);
});
