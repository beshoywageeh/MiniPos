<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('purchase invoice preview is auth protected', function () {
    $purchase = Purchase::factory()->create();

    expect(route('purchases.invoice_preview', $purchase))
        ->toBe(url('/purchases/'.$purchase->id.'/invoice-preview'));

    $this->get(route('purchases.invoice_preview', $purchase))->assertRedirect();
});

test('purchase invoice preview renders document details and payment', function () {
    $user = User::factory()->create(['name' => 'Purchase Recorder']);
    $supplier = Party::factory()->supplier()->create(['name' => 'Acme Supplier']);
    $warehouse = Warehouse::factory()->create(['name' => 'Main Store']);
    $purchase = Purchase::factory()->for($supplier, 'party')->for($warehouse)->create([
        'document_number' => 'PUR-PREVIEW',
        'total_amount' => 150,
        'paid_amount' => 50,
        'created_by' => $user->id,
    ]);
    Payment::factory()->for($purchase, 'purchase')->create([
        'party_id' => $supplier->id,
        'type' => PaymentType::payment,
        'amount' => 50,
        'method' => PaymentMethod::BANK_TRANSFER,
    ]);

    $this->actingAs($user)->get(route('purchases.invoice_preview', $purchase))
        ->assertOk()
        ->assertSee('PUR-PREVIEW')
        ->assertSee('Acme Supplier')
        ->assertSee('Main Store')
        ->assertSee('Purchase Recorder')
        ->assertSee('150.00')
        ->assertSee(PaymentMethod::BANK_TRANSFER->label());
});

test('purchase invoice preview returns 404 for a missing purchase', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('purchases.invoice_preview', 999999))
        ->assertNotFound();
});

test('party transaction history links purchase documents to the preview', function () {
    $user = User::factory()->create();
    $supplier = Party::factory()->supplier()->create();
    $purchase = Purchase::factory()->for($supplier, 'party')->create([
        'document_number' => 'PUR-PARTY',
    ]);

    $html = Livewire::actingAs($user)->test('livewire.party.show', ['party' => $supplier])->html();

    expect($html)->toContain('PUR-PARTY')
        ->toContain(route('purchases.invoice_preview', $purchase));
});

test('purchase invoice preview prints fractional quantities without rounding', function () {
    $user = User::factory()->create();
    $purchase = Purchase::factory()->create();
    $purchase->items()->create([
        'product_id' => Product::factory()->create()->id,
        'product_name' => 'Fractional Widget',
        'sku' => 'SKU-FRACTION',
        'quantity' => 0.125,
        'unit_price' => 10,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'line_total' => 1.25,
    ]);

    $this->actingAs($user)->get(route('purchases.invoice_preview', $purchase))
        ->assertOk()
        ->assertSee('Fractional Widget')
        ->assertSee('0.125');
});
