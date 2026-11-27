<?php

use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Cashbox;
use App\Models\Party;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $this->get('/sales/create')->assertRedirect(route('login'));

    $sale = Sale::factory()->create();

    $this->get(route('sales.invoice_preview', $sale))->assertRedirect(route('login'));
});

test('new sale page renders for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/sales/create');

    $response->assertOk();
    $response->assertSee(__('sales.title'));
    $response->assertSee(__('sales.add_item'));
});

test('new sale page renders the server-side product picker', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/sales/create');

    $response->assertOk();
    $response->assertSee(__('sales.search_product'));
    $response->assertSee(__('sales.draft'));
    $response->assertDontSee(__('sales.select_product'));
});

test('invoice print preview renders both paper layouts', function () {
    $user = User::factory()->create();
    $sale = Sale::factory()->create();

    $response = $this->actingAs($user)->get(route('sales.invoice_preview', $sale));

    $response->assertOk();
    $response->assertSee($sale->document_number);
    $response->assertSee(__('sales.paper_a4'));
    $response->assertSee(__('sales.paper_thermal'));
});

test('invoice preview renders the saved sale details', function () {
    $user = User::factory()->create(['name' => 'Cashier Ada']);
    $customer = Party::factory()->customer()->create([
        'name' => 'Acme Supplies',
        'phone' => '555-0100',
        'address' => '42 Warehouse Lane',
    ]);
    $sale = Sale::factory()->for($customer)->create([
        'document_number' => 'SALE-000123',
        'invoice_date' => '2026-01-15 10:30:00',
        'due_date' => '2026-01-29 10:30:00',
        'status' => DocumentStatus::PARTIAL,
        'notes' => 'Deliver after payment clears.',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('sales.invoice_preview', $sale));

    $response->assertOk();
    $response->assertSee('SALE-000123');
    $response->assertSee('Acme Supplies');
    $response->assertSee('555-0100');
    $response->assertSee('42 Warehouse Lane');
    $response->assertSee('2026-01-15');
    $response->assertSee('2026-01-29');
    $response->assertSee('Cashier Ada');
    $response->assertSee('Deliver after payment clears.');
    $response->assertSee(DocumentStatus::PARTIAL->label());
    $response->assertSee(__('sales.invoice_date'));
    $response->assertDontSee(__('dashboard.recent_sales'));
});

test('invoice preview renders saved item snapshots and stored totals', function () {
    $user = User::factory()->create();
    $sale = Sale::factory()->create([
        'subtotal' => 78.50,
        'discount_amount' => 4.00,
        'tax_amount' => 3.00,
        'total_amount' => 77.50,
        'paid_amount' => 20.00,
        'remaining_amount' => 57.50,
        'status' => DocumentStatus::PARTIAL,
    ]);
    $product = Product::factory()->create([
        'name' => 'Arabica Beans 1kg',
        'sku' => 'SKU-ABC123',
    ]);
    $sale->items()->create([
        'product_id' => $product->id,
        'product_name' => 'Arabica Beans 1kg',
        'sku' => 'SKU-ABC123',
        'quantity' => 2,
        'unit_price' => 33.00,
        'discount_amount' => 4.00,
        'tax_amount' => 3.00,
        'line_total' => 65.00,
    ]);
    $sale->items()->create([
        'product_id' => $product->id,
        'product_name' => 'Paper Filter Pack',
        'sku' => 'SKU-FILTER',
        'quantity' => 1,
        'unit_price' => 12.50,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'line_total' => 12.50,
    ]);
    $sale->payments()->create([
        'document_number' => 'PAY-000123',
        'party_id' => $sale->party_id,
        'cashbox_id' => Cashbox::factory()->create()->id,
        'type' => PaymentType::recipt,
        'method' => PaymentMethod::CASH,
        'amount' => 20.00,
        'payment_date' => $sale->invoice_date,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('sales.invoice_preview', $sale));

    $response->assertOk();
    $response->assertSee('Arabica Beans 1kg');
    $response->assertSee('SKU-ABC123');
    $response->assertSee('33.00');
    $response->assertSee('65.00');
    $response->assertSee('12.50');
    $response->assertSee('78.50');
    $response->assertSee('4.00');
    $response->assertSee('3.00');
    $response->assertSee('77.50');
    $response->assertSee('20.00');
    $response->assertSee('57.50');
    $response->assertSee(PaymentMethod::CASH->label());
});

test('invoice preview uses store settings and the configured footer', function () {
    $user = User::factory()->create();
    $sale = Sale::factory()->create();
    Settings::set('company_name', 'Bean Corner');
    Settings::set('company_phone', '+1 555 0142');
    Settings::set('company_address', '9 Roastery Road');
    Settings::set('invoice_footer', 'All sales final.');

    $response = $this->actingAs($user)->get(route('sales.invoice_preview', $sale));

    $response->assertOk();
    $response->assertSee('Bean Corner');
    $response->assertSee('+1 555 0142');
    $response->assertSee('9 Roastery Road');
    $response->assertSee('All sales final.');
    $response->assertDontSee('12 Main Street, Downtown');
    $response->assertDontSee('John Smith');
    $response->assertDontSee('INV-000001');
    $response->assertDontSee('Espresso Coffee 250g');
});

test('invoice preview falls back to defaults when store settings are blank', function () {
    $user = User::factory()->create();
    $sale = Sale::factory()->create();
    Settings::set('company_name', '');
    Settings::set('invoice_footer', '');

    $response = $this->actingAs($user)->get(route('sales.invoice_preview', $sale));

    $response->assertOk();
    $response->assertSee((string) config('app.name'));
    $response->assertSee(__('sales.thank_you'));
});

test('invoice preview omits the payment method when the sale has no receipt', function () {
    $user = User::factory()->create();
    $sale = Sale::factory()->create(['paid_amount' => 0]);

    $response = $this->actingAs($user)->get(route('sales.invoice_preview', $sale));

    $response->assertOk();
    $response->assertSee(__('sales.paid_amount').'</span>', false);
    $response->assertDontSee(__('sales.paid_amount').' (—)', false);
});

test('invoice preview escapes customer supplied content', function () {
    $user = User::factory()->create();
    $customerName = '<script>alert("xss")</script>';
    $customer = Party::factory()->customer()->create(['name' => $customerName]);
    $sale = Sale::factory()->for($customer)->create();

    $response = $this->actingAs($user)->get(route('sales.invoice_preview', $sale));

    $response->assertOk();
    $response->assertSee(e($customerName), false);
    $response->assertDontSee($customerName, false);
});

test('invoice preview renders a walk-in sale without a customer', function () {
    $user = User::factory()->create();
    $sale = Sale::factory()->create(['party_id' => null]);

    $response = $this->actingAs($user)->get(route('sales.invoice_preview', $sale));

    $response->assertOk();
    $response->assertSee(__('sales.no_customer'));
});

test('invoice preview returns 404 for a missing sale', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/sales/999999/invoice-preview')->assertNotFound();
});

test('sidebar sales link points to the sales list', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee(route('sales.index'));
});
