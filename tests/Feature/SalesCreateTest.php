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

    StockMovement::factory()->create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'movement_type' => MovementType::IN->value,
        'quantity' => 100,
        'movement_date' => today(),
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
    $movement = StockMovement::query()
        ->where('movement_type', MovementType::OUT->value)
        ->sole();
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
        ->and(StockMovement::query()->where('movement_type', MovementType::OUT->value)->count())->toBe(1);
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
        ->and(StockMovement::query()->where('movement_type', MovementType::OUT->value)->count())->toBe(0)
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

test('the sale form preselects the only active warehouse', function () {
    $component = Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->assertSet('warehouseId', $this->warehouse->id);

    expect($component->html())->toMatch('/<option value="'.$this->warehouse->id.'" selected>/');
});

test('the sale form preselects the only active cashbox', function () {
    $component = Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->assertSet('cashboxId', $this->cashbox->id);

    expect($component->html())->toMatch('/<option value="'.$this->cashbox->id.'" selected>/');
});

test('the sale form asks for a choice when several warehouses or cashboxes are active', function () {
    Warehouse::factory()->create(['name' => 'Second Warehouse']);
    Cashbox::factory()->create(['name' => 'Second Cashbox']);

    $component = Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->assertSet('warehouseId', '')
        ->assertSet('cashboxId', '');

    expect($component->html())
        ->toContain(__('sales.select_warehouse'))
        ->toMatch('/<option value="" selected>/')
        ->toContain(__('sales.select_cashbox'));
});

test('a sale saves without touching the warehouse field when only one is active', function () {
    Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('items', [saleItem($this->product->id)])
        ->call('save')
        ->assertHasNoErrors();

    expect(Sale::query()->sole()->warehouse_id)->toBe($this->warehouse->id);
});

test('saving requires a warehouse, items with products and quantities', function () {
    Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('warehouseId', '')
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

test('the product search finds active products by name', function () {
    Product::query()->whereKey($this->product->id)->update(['name' => 'Espresso Beans']);
    Product::factory()->create(['name' => 'Hidden Item', 'is_active' => false]);

    $component = Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('searchTerm', 'Espresso');

    expect($component->get('searchResults'))->toHaveCount(1)
        ->and($component->get('searchResults.0.name'))->toBe('Espresso Beans');
});

test('pressing enter adds the first search result with quantity one and the database price', function () {
    $this->product->update(['name' => 'Espresso Beans', 'selling_price' => '12.50']);

    $component = Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->call('addFirstSearchResult', 'Espresso');

    expect($component->get('items'))->toHaveCount(1)
        ->and($component->get('items.0.product_id'))->toBe($this->product->id)
        ->and($component->get('items.0.product_name'))->toBe('Espresso Beans')
        ->and($component->get('items.0.quantity'))->toBe('1')
        ->and((float) $component->get('items.0.unit_price'))->toBe(12.5)
        ->and($component->get('searchTerm'))->toBe('')
        ->and($component->get('searchResults'))->toBe([]);
});

test('clicking a search result adds that product to the invoice', function () {
    $second = Product::factory()->create(['name' => 'Paper Filters', 'selling_price' => '3.75']);

    $component = Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('searchTerm', 'Paper')
        ->call('addProductFromSearch', $second->id);

    expect($component->get('items'))->toHaveCount(1)
        ->and($component->get('items.0.product_id'))->toBe($second->id)
        ->and((float) $component->get('items.0.unit_price'))->toBe(3.75)
        ->and($component->get('searchTerm'))->toBe('');
});

test('adding the same product again increases its quantity without changing its price', function () {
    $component = Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->call('addProductFromSearch', $this->product->id)
        ->set('items.0.unit_price', '25.00')
        ->set('items.0.discount_amount', '1.50')
        ->set('items.0.tax_rate', '5')
        ->call('addProductFromSearch', $this->product->id);

    expect($component->get('items'))->toHaveCount(1)
        ->and($component->get('items.0.quantity'))->toBe('2')
        ->and((float) $component->get('items.0.unit_price'))->toBe(25.0)
        ->and((float) $component->get('items.0.discount_amount'))->toBe(1.5)
        ->and((float) $component->get('items.0.tax_rate'))->toBe(5.0);
});

test('re-adding a product keeps the incremented quantity at three decimal places', function () {
    $component = Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->call('addProductFromSearch', $this->product->id)
        ->set('items.0.quantity', '1.2345')
        ->call('addProductFromSearch', $this->product->id);

    expect($component->get('items'))->toHaveCount(1)
        ->and($component->get('items.0.quantity'))->toBe('2.234');
});

test('adding a different product creates a second invoice line', function () {
    $second = Product::factory()->create(['selling_price' => '3.75']);

    $component = Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->call('addProductFromSearch', $this->product->id)
        ->call('addProductFromSearch', $second->id);

    expect($component->get('items'))->toHaveCount(2)
        ->and($component->get('items.1.product_id'))->toBe($second->id)
        ->and($component->get('items.1.quantity'))->toBe('1')
        ->and((float) $component->get('items.1.unit_price'))->toBe(3.75);
});

test('a product that is not active cannot be added from search', function () {
    $inactive = Product::factory()->create(['is_active' => false]);

    $component = Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('searchTerm', 'Hidden')
        ->call('addProductFromSearch', $inactive->id);

    expect($component->get('items'))->toHaveCount(1)
        ->and($component->get('items.0.product_id'))->toBeNull()
        ->and($component->get('searchTerm'))->toBe('')
        ->and($component->get('searchResults'))->toBe([]);
});

test('search results can be selected with the keyboard', function () {
    $this->product->update(['name' => 'Espresso Beans']);

    Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('searchTerm', 'Espresso')
        ->assertSeeHtml(sprintf('wire:click.prevent="addProductFromSearch(%d)"', $this->product->id), false);
});

test('pressing enter with no matching product leaves the invoice unchanged', function () {
    $component = Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->call('addFirstSearchResult', 'no such product');

    expect($component->get('items'))->toHaveCount(1)
        ->and($component->get('items.0.product_id'))->toBeNull();
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
