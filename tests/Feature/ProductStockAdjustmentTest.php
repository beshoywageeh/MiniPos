<?php

use App\Enums\MovementType;
use App\Enums\StockAdjustmentReason;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function productAdjustmentPayload(array $overrides = []): array
{
    return array_merge([
        'movementType' => MovementType::IN->value,
        'quantity' => '1',
        'unitCost' => '',
        'reason' => StockAdjustmentReason::CountCorrection->value,
        'note' => 'Count correction',
        'isOverride' => false,
    ], $overrides);
}

test('stock can be adjusted in for a product', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.catalog.products.actions.adjust', ['product' => $product])
        ->set('warehouseId', $warehouse->id)
        ->set(productAdjustmentPayload([
            'movementType' => MovementType::IN->value,
            'quantity' => '7.5',
            'unitCost' => '3.25',
            'note' => 'Count correction',
        ]))
        ->call('adjust')
        ->assertDispatched('product-adjusted')
        ->assertHasNoErrors();

    $adjustment = StockAdjustment::query()->sole();
    $movement = StockMovement::query()->sole();

    expect($adjustment->document_number)->toStartWith('STADJ-')
        ->and($adjustment->reason)->toBe(StockAdjustmentReason::CountCorrection)
        ->and($adjustment->is_override)->toBeFalse()
        ->and($movement->product_id)->toBe($product->id)
        ->and($movement->warehouse_id)->toBe($warehouse->id)
        ->and($movement->movement_type)->toBe(MovementType::IN->value)
        ->and((float) $movement->quantity)->toBe(7.5)
        ->and((float) $movement->unit_cost)->toBe(3.25)
        ->and($movement->note)->toBe('Count correction')
        ->and($movement->reference_type)->toBe(StockAdjustment::class)
        ->and($movement->reference_id)->toBe($adjustment->id)
        ->and($movement->is_override)->toBeFalse()
        ->and($movement->created_by)->toBe($user->id);
});

test('stock can be adjusted out and defaults the unit cost to zero', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.catalog.products.actions.adjust', ['product' => $product])
        ->set('warehouseId', $warehouse->id)
        ->set(productAdjustmentPayload([
            'movementType' => MovementType::OUT->value,
            'quantity' => '2',
            'note' => 'Damaged in transit.',
            'isOverride' => true,
        ]))
        ->call('adjust')
        ->assertDispatched('product-adjusted')
        ->assertHasNoErrors();

    $movement = StockMovement::query()->sole();

    expect($movement->movement_type)->toBe(MovementType::OUT->value)
        ->and((float) $movement->unit_cost)->toBe(0.0)
        ->and($movement->is_override)->toBeTrue();
});

test('product adjustment records the selected reason', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    $reason = StockAdjustmentReason::Damage;

    Livewire::actingAs($user)
        ->test('livewire.catalog.products.actions.adjust', ['product' => $product])
        ->set('warehouseId', $warehouse->id)
        ->set(productAdjustmentPayload([
            'reason' => $reason->value,
            'note' => $reason->label(),
        ]))
        ->call('adjust')
        ->assertHasNoErrors();

    expect(StockAdjustment::query()->sole()->reason)->toBe($reason)
        ->and(StockMovement::query()->sole()->note)->toBe($reason->label());
});

test('stock adjustment requires warehouse, direction, quantity, reason and date', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.catalog.products.actions.adjust', ['product' => $product])
        ->set('quantity', '')
        ->set('reason', '')
        ->set('movementDate', '')
        ->call('adjust')
        ->assertHasErrors([
            'warehouseId' => 'required',
            'quantity' => 'required',
            'reason' => 'required',
            'movementDate' => 'required',
        ])
        ->assertNotDispatched('product-adjusted');
});

test('stock adjustment rejects zero and negative quantities', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.catalog.products.actions.adjust', ['product' => $product])
        ->set('warehouseId', $warehouse->id)
        ->set(productAdjustmentPayload(['quantity' => '0']))
        ->call('adjust')
        ->assertHasErrors(['quantity' => 'min'])
        ->assertNotDispatched('product-adjusted');
});

test('product adjustment rejects insufficient stock unless overridden', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.catalog.products.actions.adjust', ['product' => $product])
        ->set('warehouseId', $warehouse->id)
        ->set(productAdjustmentPayload([
            'movementType' => MovementType::OUT->value,
            'quantity' => '2',
            'note' => '',
        ]))
        ->call('adjust')
        ->assertHasErrors(['quantity'])
        ->assertNotDispatched('product-adjusted');

    expect(StockAdjustment::query()->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(0);

    Livewire::actingAs($user)
        ->test('livewire.catalog.products.actions.adjust', ['product' => $product])
        ->set('warehouseId', $warehouse->id)
        ->set(productAdjustmentPayload([
            'movementType' => MovementType::OUT->value,
            'quantity' => '2',
            'note' => 'Damaged in transit.',
            'isOverride' => true,
        ]))
        ->call('adjust')
        ->assertDispatched('product-adjusted')
        ->assertHasNoErrors();

    $adjustment = StockAdjustment::query()->sole();
    $movement = StockMovement::query()->sole();

    expect($adjustment->is_override)->toBeTrue()
        ->and($movement->is_override)->toBeTrue()
        ->and((float) $movement->balance_after)->toBe(-2.0);
});

test('stock adjustment defaults the date to today', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('livewire.catalog.products.actions.adjust', ['product' => $product]);

    expect($component->get('movementDate'))->toBe(today()->format('Y-m-d'));

    $component->set('warehouseId', $warehouse->id)
        ->set(productAdjustmentPayload())
        ->call('adjust')
        ->assertHasNoErrors();

    expect(StockMovement::query()->sole()->movement_date)->toContain(today()->format('Y-m-d'));
});
