<?php

use App\Enums\MovementType;
use App\Enums\StockAdjustmentReason;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\RecordStockAdjustment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function adjustmentAttributes(array $overrides = []): array
{
    return array_merge([
        'product_id' => Product::factory()->create()->id,
        'warehouse_id' => Warehouse::factory()->create()->id,
        'movement_type' => MovementType::IN,
        'quantity' => '2',
        'unit_cost' => '0',
        'reason' => StockAdjustmentReason::CountCorrection,
        'note' => null,
        'movement_date' => today()->toDateString(),
        'is_override' => false,
        'created_by' => User::factory()->create()->id,
    ], $overrides);
}

test('in adjustment creates a numbered document and linked movement', function () {
    $adjustment = RecordStockAdjustment::execute(adjustmentAttributes([
        'note' => 'Found two extra units during the count.',
    ]));

    $movement = $adjustment->movement()->firstOrFail();

    expect($adjustment->document_number)->toStartWith('STADJ-')
        ->and($adjustment->movement_type)->toBe(MovementType::IN)
        ->and($adjustment->reason)->toBe(StockAdjustmentReason::CountCorrection)
        ->and($adjustment->note)->toBe('Found two extra units during the count.')
        ->and($movement->reference_type)->toBe(StockAdjustment::class)
        ->and($movement->reference_id)->toBe($adjustment->id)
        ->and($movement->movement_type)->toBe(MovementType::IN->value)
        ->and($movement->balance_after)->not->toBeNull()
        ->and((float) $movement->balance_after)->toBe(2.0)
        ->and($adjustment->is_override)->toBeFalse()
        ->and($movement->is_override)->toBeFalse();
});

test('out adjustment succeeds when stock is sufficient', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'movement_type' => MovementType::IN,
        'quantity' => 10,
    ]);

    $adjustment = RecordStockAdjustment::execute(adjustmentAttributes([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'movement_type' => MovementType::OUT,
        'quantity' => '3',
    ]));

    $movement = $adjustment->movement()->firstOrFail();

    expect($adjustment->document_number)->toStartWith('STADJ-')
        ->and($movement->movement_type)->toBe(MovementType::OUT->value)
        ->and($movement->reference_type)->toBe(StockAdjustment::class)
        ->and($movement->reference_id)->toBe($adjustment->id)
        ->and((float) $movement->balance_after)->toBe(7.0);
});

test('out adjustment beyond available stock is rejected', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'movement_type' => MovementType::IN,
        'quantity' => 1,
    ]);

    try {
        RecordStockAdjustment::execute(adjustmentAttributes([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'movement_type' => MovementType::OUT,
            'quantity' => '2',
        ]));
        $this->fail('Expected an insufficient-stock validation error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('quantity');
    }

    expect(StockAdjustment::query()->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(1);
});

test('override cannot be used without a note', function () {
    try {
        RecordStockAdjustment::execute(adjustmentAttributes([
            'movement_type' => MovementType::OUT,
            'is_override' => true,
            'note' => '',
        ]));
        $this->fail('Expected an override-note validation error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('note');
    }

    expect(StockAdjustment::query()->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(0);
});

test('override records the flag on adjustment and movement', function () {
    $adjustment = RecordStockAdjustment::execute(adjustmentAttributes([
        'movement_type' => MovementType::OUT,
        'is_override' => true,
        'note' => 'Damaged in transit.',
    ]));

    $movement = $adjustment->movement()->firstOrFail();

    expect($adjustment->is_override)->toBeTrue()
        ->and($movement->is_override)->toBeTrue()
        ->and($movement->note)->toBe('Damaged in transit.')
        ->and((float) $movement->balance_after)->toBe(-2.0);
});

test('service products cannot be adjusted', function () {
    $service = Product::factory()->service()->create();

    try {
        RecordStockAdjustment::execute(adjustmentAttributes([
            'product_id' => $service->id,
        ]));
        $this->fail('Expected a service-product validation error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('product_id');
    }

    expect(StockAdjustment::query()->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(0);
});
