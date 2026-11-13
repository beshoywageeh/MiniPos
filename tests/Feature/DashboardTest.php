<?php

use App\Models\Cashbox;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
});

test('dashboard shows today sales, purchases and expenses totals', function () {
    $user = User::factory()->create();

    Sale::factory()->create([
        'invoice_date' => today(),
        'total_amount' => 115.50,
    ]);
    Sale::factory()->create([
        'invoice_date' => today()->subDay(),
        'total_amount' => 999.99,
    ]);
    Purchase::factory()->create([
        'invoice_date' => today(),
        'total_amount' => 40.25,
    ]);
    $cashbox = Cashbox::factory()->create(['opening_balance' => 500.00]);
    Expense::factory()->create([
        'expense_date' => today(),
        'amount' => 12.10,
        'cashbox_id' => $cashbox,
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee(number_format(115.50, 2));
    $response->assertSee(number_format(40.25, 2));
    $response->assertSee(number_format(12.10, 2));
    $response->assertSee(number_format(500.00, 2));
    $response->assertSee('999.99');
});

test('dashboard lists products at or below their minimum stock level', function () {
    $user = User::factory()->create();

    $lowStockProduct = Product::factory()->create(['name' => 'Low Stock Widget', 'min_stock' => 10]);
    $healthyStockProduct = Product::factory()->create(['name' => 'Healthy Stock Widget', 'min_stock' => 5]);

    StockMovement::factory()->create([
        'product_id' => $lowStockProduct,
        'movement_type' => 'in',
        'quantity' => 5,
    ]);
    StockMovement::factory()->create([
        'product_id' => $healthyStockProduct,
        'movement_type' => 'in',
        'quantity' => 100,
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('Low Stock Widget');
    $response->assertDontSee('Healthy Stock Widget');
});
