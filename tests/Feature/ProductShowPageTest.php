<?php

use App\Enums\MovementType;
use App\Enums\ProductType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('product show page route exists and requires auth', function () {
    $product = Product::factory()->create();

    expect(route('products.show', $product))->toBe(url('/products/'.$product->id));

    $this->get(route('products.show', $product))->assertRedirect();
});

test('product show page renders product details', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['name' => 'Beverages']);
    $brand = Brand::factory()->create(['name' => 'Acme Roasters']);
    $unit = Unit::factory()->create(['name' => 'Kilogram']);
    $product = Product::factory()->create([
        'name' => 'Espresso Beans',
        'code' => 'PRD-SHOW',
        'sku' => 'SKU-SHOW',
        'barcode' => '1111111111111',
        'description' => 'Dark roast beans',
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'unit_id' => $unit->id,
    ]);

    Livewire::actingAs($user)->test('livewire.catalog.products.show', ['product' => $product])
        ->assertSee('Espresso Beans')
        ->assertSee('PRD-SHOW')
        ->assertSee('SKU-SHOW')
        ->assertSee('1111111111111')
        ->assertSee('Dark roast beans')
        ->assertSee('Beverages')
        ->assertSee('Acme Roasters')
        ->assertSee('Kilogram');
});

test('product show page breaks stock down per warehouse with a total', function () {
    $user = User::factory()->create();
    $mainWarehouse = Warehouse::factory()->create(['name' => 'Main Store']);
    $annexWarehouse = Warehouse::factory()->create(['name' => 'Annex']);
    $product = Product::factory()->create(['min_stock' => 5]);

    StockMovement::factory()->for($product)->create([
        'warehouse_id' => $mainWarehouse->id,
        'movement_type' => MovementType::IN,
        'quantity' => 20,
    ]);
    StockMovement::factory()->for($product)->create([
        'warehouse_id' => $mainWarehouse->id,
        'movement_type' => MovementType::OUT,
        'quantity' => 5,
    ]);
    StockMovement::factory()->for($product)->create([
        'warehouse_id' => $annexWarehouse->id,
        'movement_type' => MovementType::IN,
        'quantity' => 3.5,
    ]);

    Livewire::actingAs($user)->test('livewire.catalog.products.show', ['product' => $product])
        ->assertSee('Main Store')
        ->assertSee('Annex')
        ->assertSee('15.000')
        ->assertSee('3.500')
        ->assertSee('18.500');
});

test('product show page flags low total stock', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create(['min_stock' => 50]);

    StockMovement::factory()->for($product)->create([
        'warehouse_id' => $warehouse->id,
        'movement_type' => MovementType::IN,
        'quantity' => 10,
    ]);

    Livewire::actingAs($user)->test('livewire.catalog.products.show', ['product' => $product])
        ->assertSee('Low stock');
});

test('product show page lists movement history', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create(['name' => 'Main Store']);
    $product = Product::factory()->create();

    StockMovement::factory()->for($product)->create([
        'warehouse_id' => $warehouse->id,
        'movement_type' => MovementType::IN,
        'quantity' => 12.5,
        'unit_cost' => 4.75,
        'note' => 'Initial delivery',
    ]);

    Livewire::actingAs($user)->test('livewire.catalog.products.show', ['product' => $product])
        ->assertSee('Initial delivery')
        ->assertSee('12.500')
        ->assertSee('4.75');
});

test('service products show no stock section data', function () {
    $user = User::factory()->create();
    $product = Product::factory()->service()->create(['name' => 'Delivery Service']);

    Livewire::actingAs($user)->test('livewire.catalog.products.show', ['product' => $product])
        ->assertSee('Delivery Service')
        ->assertDontSee('Low stock');
});

test('a missing product id returns 404', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('products.show', 999))->assertNotFound();
});

test('a soft deleted product returns 404', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $product->delete();

    $this->actingAs($user)->get(route('products.show', $product))->assertNotFound();
});

test('product type is shown on the show page', function () {
    $user = User::factory()->create();
    $product = Product::factory()->service()->create();

    Livewire::actingAs($user)->test('livewire.catalog.products.show', ['product' => $product])
        ->assertSee(ProductType::Service->label());
});
