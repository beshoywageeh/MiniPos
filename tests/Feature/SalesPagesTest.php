<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $this->get('/sales/create')->assertRedirect(route('login'));
    $this->get('/sales/invoice-preview')->assertRedirect(route('login'));
});

test('new sale page renders for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/sales/create');

    $response->assertOk();
    $response->assertSee(__('sales.title'));
    $response->assertSee(__('sales.add_item'));
    $response->assertSee(route('sales.invoice_preview'));
});

test('new sale page product picker points at the product lookup endpoint', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/sales/create');

    $response->assertOk();
    $response->assertSee(route('products.lookup'));
    $response->assertSee(__('sales.search_product'));
    $response->assertDontSee(__('sales.select_product'));
});

test('invoice print preview renders both paper layouts', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/sales/invoice-preview');

    $response->assertOk();
    $response->assertSee('INV-000001');
    $response->assertSee(__('sales.paper_a4'));
    $response->assertSee(__('sales.paper_thermal'));
});

test('sidebar sales link points to the new sale page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee(route('sales.create'));
});
