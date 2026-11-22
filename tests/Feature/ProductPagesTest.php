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

test('a product type is cast and defaults to product', function () {
    $product = Product::factory()->create();
    $service = Product::factory()->service()->create();

    expect($product->type)->toBe(ProductType::Product)
        ->and($service->type)->toBe(ProductType::Service);
});

test('products page route exists and requires auth', function () {
    expect(route('products.index'))->toBe(url('/products'));

    $this->get(route('products.index'))->assertRedirect();
});

test('products page lists products with classification and type', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['name' => 'Beverages']);
    $brand = Brand::factory()->create(['name' => 'Acme Roasters']);
    $unit = Unit::factory()->create(['name' => 'Kilogram']);
    Product::factory()->create([
        'name' => 'Espresso Beans',
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'unit_id' => $unit->id,
        'type' => ProductType::Service,
    ]);

    Livewire::actingAs($user)->test('livewire.catalog.products.index')
        ->assertSee('Espresso Beans')
        ->assertSee('Beverages')
        ->assertSee('Acme Roasters')
        ->assertSee('Kilogram')
        ->assertSee('Service');
});

test('products index shows current stock and the low stock badge', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create([
        'name' => 'Filter Papers',
        'type' => ProductType::Product,
        'min_stock' => 10,
    ]);

    StockMovement::factory()->for($product)->create([
        'warehouse_id' => $warehouse->id,
        'movement_type' => MovementType::IN,
        'quantity' => 15.25,
    ]);
    StockMovement::factory()->for($product)->create([
        'warehouse_id' => $warehouse->id,
        'movement_type' => MovementType::OUT,
        'quantity' => 8,
    ]);

    Livewire::actingAs($user)->test('livewire.catalog.products.index')
        ->assertSee('7.25')
        ->assertSee('Low stock');
});

test('a product with no movements is treated as zero stock and flagged low', function () {
    $user = User::factory()->create();
    Product::factory()->create([
        'name' => 'Bulk Coffee',
        'type' => ProductType::Product,
        'min_stock' => 100,
    ]);

    Livewire::actingAs($user)->test('livewire.catalog.products.index')
        ->assertSee('Bulk Coffee')
        ->assertSee('Low stock');
});

test('service products show a dash for stock and are never low stock', function () {
    $user = User::factory()->create();
    Product::factory()->service()->create([
        'name' => 'Delivery Service',
        'min_stock' => 100,
    ]);

    Livewire::actingAs($user)->test('livewire.catalog.products.index')
        ->assertSee('—')
        ->assertDontSee('Low stock');
});

test('products search matches the given field', function (string $value) {
    $user = User::factory()->create();
    Product::factory()->create([
        'name' => 'Espresso Beans',
        'code' => 'PRD-1111',
        'sku' => 'SKU-ALPHA',
        'barcode' => '1111111111111',
    ]);
    Product::factory()->create([
        'name' => 'Green Tea',
        'code' => 'PRD-2222',
        'sku' => 'SKU-BETA',
        'barcode' => '2222222222222',
    ]);

    Livewire::actingAs($user)->test('livewire.catalog.products.index')
        ->set('search', $value)
        ->assertSee('Espresso Beans')
        ->assertDontSee('Green Tea');
})->with([
    'name' => ['Espresso'],
    'code' => ['PRD-1111'],
    'sku' => ['SKU-ALPHA'],
    'barcode' => ['1111111111111'],
]);

test('products search with no match shows the empty state', function () {
    $user = User::factory()->create();
    Product::factory()->create(['name' => 'Espresso Beans']);

    Livewire::actingAs($user)->test('livewire.catalog.products.index')
        ->set('search', 'zzzz')
        ->assertSee('No products found.');
});

test('products list refreshes when an event fires', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Before Update']);

    $component = Livewire::actingAs($user)->test('livewire.catalog.products.index');

    expect($component->html())->toContain('Before Update');

    $product->update(['name' => 'After Update']);

    $component->dispatch('product-updated');

    expect($component->html())->toContain('After Update');
});

test('a product can be created with classification and pricing', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    $brand = Brand::factory()->create();
    $unit = Unit::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.catalog.products.actions.create')
        ->set('code', 'PRD-TEST')
        ->set('type', ProductType::Service->value)
        ->set('sku', '')
        ->set('barcode', '')
        ->set('name', 'Consulting Hour')
        ->set('description', 'On-site help')
        ->set('categoryId', $category->id)
        ->set('brandId', $brand->id)
        ->set('unitId', $unit->id)
        ->set('purchasePrice', '0')
        ->set('sellingPrice', '25.50')
        ->set('minStock', '0')
        ->set('isActive', false)
        ->call('create')
        ->assertDispatched('product-created')
        ->assertHasNoErrors();

    $product = Product::where('code', 'PRD-TEST')->firstOrFail();

    expect($product->type)->toBe(ProductType::Service)
        ->and($product->name)->toBe('Consulting Hour')
        ->and($product->category_id)->toBe($category->id)
        ->and($product->brand_id)->toBe($brand->id)
        ->and($product->unit_id)->toBe($unit->id)
        ->and($product->sku)->toBeNull()
        ->and($product->barcode)->toBeNull()
        ->and($product->is_active)->toBeFalse();
});

test('several products can be created with empty sku and barcode', function () {
    $user = User::factory()->create();

    foreach (['PRD-BLANK-1', 'PRD-BLANK-2'] as $code) {
        Livewire::actingAs($user)
            ->test('livewire.catalog.products.actions.create')
            ->set('code', $code)
            ->set('name', "Blank {$code}")
            ->set('sku', '')
            ->set('barcode', '')
            ->set('purchasePrice', '1')
            ->set('sellingPrice', '2')
            ->set('minStock', '0')
            ->call('create')
            ->assertHasNoErrors()
            ->assertDispatched('product-created');
    }

    expect(Product::whereIn('code', ['PRD-BLANK-1', 'PRD-BLANK-2'])
        ->whereNull('sku')
        ->whereNull('barcode')
        ->count())->toBe(2);
});

test('product creation rejects duplicate code, sku and barcode plus missing required fields', function () {
    Product::factory()->create([
        'code' => 'PRD-TAKEN',
        'sku' => 'SKU-TAKEN',
        'barcode' => '9999999999999',
    ]);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.catalog.products.actions.create')
        ->set('code', 'PRD-TAKEN')
        ->set('sku', 'SKU-TAKEN')
        ->set('barcode', '9999999999999')
        ->set('name', '')
        ->set('purchasePrice', '')
        ->set('sellingPrice', '')
        ->set('minStock', '')
        ->call('create')
        ->assertHasErrors([
            'code' => 'unique',
            'sku' => 'unique',
            'barcode' => 'unique',
            'name' => 'required',
            'purchasePrice' => 'required',
            'sellingPrice' => 'required',
            'minStock' => 'required',
        ])
        ->assertNotDispatched('product-created');
});
