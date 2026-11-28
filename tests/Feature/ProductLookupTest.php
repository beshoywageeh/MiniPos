<?php

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('product lookup requires auth', function () {
    $this->getJson(route('products.lookup', ['q' => 'espresso']))->assertStatus(401);
});

test('product lookup returns matches by barcode, sku, code and name', function (string $query) {
    $user = User::factory()->create();
    Product::factory()->create([
        'name' => 'Espresso Beans',
        'code' => 'PRD-1111',
        'sku' => 'SKU-ALPHA',
        'barcode' => '1111111111111',
    ]);
    Product::factory()->create(['name' => 'Green Tea', 'code' => 'PRD-2222']);

    $response = $this->actingAs($user)->getJson(route('products.lookup', ['q' => $query]));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name'))->toBe('Espresso Beans');
})->with([
    'barcode' => ['1111111111111'],
    'sku' => ['SKU-ALPHA'],
    'code' => ['PRD-1111'],
    'name' => ['Espresso'],
]);

test('product lookup returns selling price, type and unit', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['selling_price' => '12.50']);

    $response = $this->actingAs($user)->getJson(route('products.lookup', ['q' => $product->code]));

    expect($response->json('data.0.selling_price'))->toBe(12.5)
        ->and($response->json('data.0.type'))->toBe(ProductType::Product->value)
        ->and($response->json('data.0.is_service'))->toBeFalse()
        ->and($response->json('data.0.unit_name'))->toBeString();
});

test('product lookup flags service products', function () {
    $user = User::factory()->create();
    Product::factory()->service()->create(['name' => 'Delivery Service']);

    $response = $this->actingAs($user)->getJson(route('products.lookup', ['q' => 'Delivery']));

    expect($response->json('data.0.is_service'))->toBeTrue();
});

test('product lookup only returns active products', function () {
    $user = User::factory()->create();
    Product::factory()->create(['name' => 'Hidden Item', 'is_active' => false]);
    Product::factory()->create(['name' => 'Visible Item']);

    $response = $this->actingAs($user)->getJson(route('products.lookup', ['q' => 'Item']));

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name'))->toBe('Visible Item');
});

test('product lookup limits results to ten', function () {
    $user = User::factory()->create();
    Product::factory()->count(12)->create(['name' => 'Bulk Item']);

    $response = $this->actingAs($user)->getJson(route('products.lookup', ['q' => 'Bulk']));

    expect($response->json('data'))->toHaveCount(10);
});

test('product lookup with no match returns an empty list', function () {
    $user = User::factory()->create();
    Product::factory()->create(['name' => 'Espresso Beans']);

    $response = $this->actingAs($user)->getJson(route('products.lookup', ['q' => 'zzzz']));

    $response->assertOk();
    expect($response->json('data'))->toBe([]);
});

test('product lookup puts an exact barcode match first', function () {
    $user = User::factory()->create();
    Product::factory()->create(['name' => 'AAA Match', 'barcode' => '9999999999991']);
    $exact = Product::factory()->create(['name' => 'ZZZ Exact', 'barcode' => '9999999999999']);

    $response = $this->actingAs($user)->getJson(route('products.lookup', ['q' => '9999999999999']));

    expect($response->json('data.0.id'))->toBe($exact->id);
});
