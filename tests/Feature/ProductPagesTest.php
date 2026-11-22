<?php

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
