<?php

use App\Enums\MovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('stock can be adjusted in for a product', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.catalog.products.actions.adjust', ['product' => $product])
        ->set('warehouseId', $warehouse->id)
        ->set('movementType', MovementType::IN->value)
        ->set('quantity', '7.5')
        ->set('unitCost', '3.25')
        ->set('note', 'Count correction')
        ->call('adjust')
        ->assertDispatched('product-adjusted')
        ->assertHasNoErrors();

    $movement = StockMovement::query()->sole();

    expect($movement->product_id)->toBe($product->id)
        ->and($movement->warehouse_id)->toBe($warehouse->id)
        ->and($movement->movement_type)->toBe(MovementType::IN->value)
        ->and((float) $movement->quantity)->toBe(7.5)
        ->and((float) $movement->unit_cost)->toBe(3.25)
        ->and($movement->note)->toBe('Count correction')
        ->and($movement->reference_type)->toBe('adjustment')
        ->and($movement->created_by)->toBe($user->id);
});

test('stock can be adjusted out and defaults the unit cost to zero', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.catalog.products.actions.adjust', ['product' => $product])
        ->set('warehouseId', $warehouse->id)
        ->set('movementType', MovementType::OUT->value)
        ->set('quantity', '2')
        ->set('unitCost', '')
        ->call('adjust')
        ->assertDispatched('product-adjusted')
        ->assertHasNoErrors();

    $movement = StockMovement::query()->sole();

    expect($movement->movement_type)->toBe(MovementType::OUT->value)
        ->and((float) $movement->unit_cost)->toBe(0.0);
});

test('stock adjustment requires warehouse, direction, quantity and date', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.catalog.products.actions.adjust', ['product' => $product])
        ->set('quantity', '')
        ->set('movementDate', '')
        ->call('adjust')
        ->assertHasErrors([
            'warehouseId' => 'required',
            'quantity' => 'required',
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
        ->set('quantity', '0')
        ->call('adjust')
        ->assertHasErrors(['quantity' => 'min'])
        ->assertNotDispatched('product-adjusted');
});

test('stock adjustment defaults the date to today', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('livewire.catalog.products.actions.adjust', ['product' => $product]);

    expect($component->get('movementDate'))->toBe(today()->format('Y-m-d'));

    $component->set('warehouseId', $warehouse->id)
        ->set('quantity', '1')
        ->call('adjust')
        ->assertHasNoErrors();

    expect(StockMovement::query()->sole()->movement_date)->toContain(today()->format('Y-m-d'));
});
