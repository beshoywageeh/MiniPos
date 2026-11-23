<?php

use App\Enums\DocumentStatus;
use App\Enums\MovementType;
use App\Enums\PaymentType;
use App\Models\Cashbox;
use App\Models\DocumentSequence;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->customer = Party::factory()->customer()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->cashbox = Cashbox::factory()->create();
    $this->product = Product::factory()->create([
        'selling_price' => '10.00',
        'purchase_price' => '6.00',
    ]);

    DocumentSequence::factory()->forDocumentType('sale', 'SALE')->create([
        'next_number' => 1,
        'padding' => 6,
    ]);
    DocumentSequence::factory()->forDocumentType('payment', 'PAY')->create([
        'next_number' => 1,
        'padding' => 6,
    ]);
});

/**
 * @return array<string, mixed>
 */
function saleItem(int $productId, string $quantity = '2', string $unitPrice = '10.00', string $discount = '', string $taxRate = ''): array
{
    return [
        'product_id' => $productId,
        'product_name' => '',
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'discount_amount' => $discount,
        'tax_rate' => $taxRate,
    ];
}

test('a paid in full sale is saved with a document number, snapshots and derived status', function () {
    Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('partyId', $this->customer->id)
        ->set('warehouseId', $this->warehouse->id)
        ->set('cashboxId', $this->cashbox->id)
        ->set('paymentMethod', 'cash')
        ->set('paidAmount', '22.00')
        ->set('items', [saleItem($this->product->id, quantity: '2', taxRate: '10')])
        ->call('save')
        ->assertHasNoErrors();

    $sale = Sale::query()->sole();

    expect($sale->document_number)->toBe('SALE-000001')
        ->and($sale->status)->toBe(DocumentStatus::PAID)
        ->and((float) $sale->subtotal)->toBe(20.0)
        ->and((float) $sale->tax_amount)->toBe(2.0)
        ->and((float) $sale->total_amount)->toBe(22.0)
        ->and((float) $sale->paid_amount)->toBe(22.0)
        ->and((float) $sale->remaining_amount)->toBe(0.0)
        ->and($sale->created_by)->toBe($this->user->id);

    $item = SaleItem::query()->sole();

    expect($item->product_name)->toBe($this->product->name)
        ->and($item->sku)->toBe($this->product->sku)
        ->and((float) $item->cost_price)->toBe(6.0)
        ->and((float) $item->line_total)->toBe(22.0);
});

test('a paid sale writes out movements and a receipt payment', function () {
    Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('partyId', $this->customer->id)
        ->set('warehouseId', $this->warehouse->id)
        ->set('cashboxId', $this->cashbox->id)
        ->set('paidAmount', '20.00')
        ->set('items', [saleItem($this->product->id)])
        ->call('save')
        ->assertHasNoErrors();

    $sale = Sale::query()->sole();
    $movement = StockMovement::query()->sole();
    $payment = Payment::query()->sole();

    expect($movement->movement_type)->toBe(MovementType::OUT->value)
        ->and((float) $movement->quantity)->toBe(2.0)
        ->and((float) $movement->unit_cost)->toBe(6.0)
        ->and($movement->reference_type)->toBe(Sale::class)
        ->and($movement->reference_id)->toBe($sale->id)
        ->and($movement->warehouse_id)->toBe($this->warehouse->id)
        ->and($payment->type)->toBe(PaymentType::recipt)
        ->and($payment->sale_id)->toBe($sale->id)
        ->and($payment->party_id)->toBe($this->customer->id)
        ->and($payment->cashbox_id)->toBe($this->cashbox->id)
        ->and((float) $payment->amount)->toBe(20.0)
        ->and($payment->document_number)->toBe('PAY-000001');
});

test('a partially paid sale is marked partial with a remaining amount', function () {
    Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('partyId', $this->customer->id)
        ->set('warehouseId', $this->warehouse->id)
        ->set('cashboxId', $this->cashbox->id)
        ->set('paidAmount', '5.00')
        ->set('items', [saleItem($this->product->id)])
        ->call('save')
        ->assertHasNoErrors();

    $sale = Sale::query()->sole();

    expect($sale->status)->toBe(DocumentStatus::PARTIAL)
        ->and((float) $sale->remaining_amount)->toBe(15.0)
        ->and(Payment::query()->count())->toBe(1);
});

test('an unpaid sale is marked pending with no payment', function () {
    Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('partyId', $this->customer->id)
        ->set('warehouseId', $this->warehouse->id)
        ->set('paidAmount', '0')
        ->set('items', [saleItem($this->product->id)])
        ->call('save')
        ->assertHasNoErrors();

    expect(Sale::query()->sole()->status)->toBe(DocumentStatus::PENDING)
        ->and(Payment::query()->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(1);
});

test('a draft sale gets no movements and no payment', function () {
    Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('partyId', $this->customer->id)
        ->set('warehouseId', $this->warehouse->id)
        ->set('isDraft', true)
        ->set('items', [saleItem($this->product->id)])
        ->call('save')
        ->assertHasNoErrors();

    $sale = Sale::query()->sole();

    expect($sale->status)->toBe(DocumentStatus::DRAFT)
        ->and((float) $sale->paid_amount)->toBe(0.0)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0);
});

test('consecutive sales get consecutive document numbers', function () {
    foreach ([0, 1] as $i) {
        Livewire::actingAs($this->user)
            ->test('livewire.sales.create')
            ->set('partyId', $this->customer->id)
            ->set('warehouseId', $this->warehouse->id)
            ->set('cashboxId', $this->cashbox->id)
            ->set('paidAmount', '20.00')
            ->set('items', [saleItem($this->product->id)])
            ->call('save')
            ->assertHasNoErrors();
    }

    expect(Sale::query()->orderBy('id')->pluck('document_number')->all())
        ->toBe(['SALE-000001', 'SALE-000002']);
});

test('saving requires a warehouse, items with products and quantities', function () {
    Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('items', [])
        ->call('save')
        ->assertHasErrors([
            'warehouseId' => 'required',
            'items' => 'required',
        ]);

    expect(Sale::query()->count())->toBe(0);
});

test('saving rejects an item without a product or with a zero quantity', function () {
    Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('warehouseId', $this->warehouse->id)
        ->set('items', [saleItem(0, quantity: '0')])
        ->call('save')
        ->assertHasErrors([
            'items.0.product_id' => 'exists',
            'items.0.quantity' => 'min',
        ]);

    expect(Sale::query()->count())->toBe(0);
});

test('a non draft sale requires a cashbox and payment method when paid', function () {
    Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('warehouseId', $this->warehouse->id)
        ->set('cashboxId', '')
        ->set('paidAmount', '20.00')
        ->set('items', [saleItem($this->product->id)])
        ->call('save')
        ->assertHasErrors([
            'cashboxId' => 'required',
        ]);

    expect(Sale::query()->count())->toBe(0);
});

test('the paid amount cannot exceed the grand total', function () {
    Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('warehouseId', $this->warehouse->id)
        ->set('cashboxId', $this->cashbox->id)
        ->set('paidAmount', '25.00')
        ->set('items', [saleItem($this->product->id)])
        ->call('save')
        ->assertHasErrors(['paidAmount']);

    expect(Sale::query()->count())->toBe(0);
});

test('the due date cannot precede the invoice date', function () {
    Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('warehouseId', $this->warehouse->id)
        ->set('invoiceDate', '2026-11-20')
        ->set('dueDate', '2026-11-19')
        ->set('items', [saleItem($this->product->id)])
        ->call('save')
        ->assertHasErrors(['dueDate' => 'after_or_equal']);
});

test('the product search finds active products and picking fills the row', function () {
    Product::query()->whereKey($this->product->id)->update(['name' => 'Espresso Beans']);
    $inactive = Product::factory()->create(['name' => 'Hidden Item', 'is_active' => false]);

    $component = Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('searchingItemIndex', 0)
        ->set('searchTerm', 'Espresso');

    expect($component->get('searchResults'))->toHaveCount(1)
        ->and($component->get('searchResults.0.name'))->toBe('Espresso Beans');

    $component->call('pickProduct', 0, $this->product->id);

    expect($component->get('items.0.product_id'))->toBe($this->product->id)
        ->and($component->get('items.0.product_name'))->toBe('Espresso Beans')
        ->and((float) $component->get('items.0.unit_price'))->toBe(10.0)
        ->and($component->get('searchResults'))->toBe([])
        ->and($component->get('searchingItemIndex'))->toBeNull()
        ->and(Product::find($inactive->id)->name)->toBe('Hidden Item');
});

test('a walk-in customer sale is saved without a party', function () {
    Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('partyId', '')
        ->set('warehouseId', $this->warehouse->id)
        ->set('cashboxId', $this->cashbox->id)
        ->set('paidAmount', '20.00')
        ->set('items', [saleItem($this->product->id)])
        ->call('save')
        ->assertHasNoErrors();

    expect(Sale::query()->sole()->party_id)->toBeNull();
});

test('saving a sale redirects to that sale invoice preview', function () {
    Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('warehouseId', $this->warehouse->id)
        ->set('cashboxId', $this->cashbox->id)
        ->set('paidAmount', '22.00')
        ->set('items', [saleItem($this->product->id, quantity: '2', taxRate: '10')])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('sales.invoice_preview', Sale::query()->sole()));
});
