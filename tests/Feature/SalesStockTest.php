<?php

use App\Enums\DocumentStatus;
use App\Enums\MovementType;
use App\Models\Cashbox;
use App\Models\DocumentSequence;
use App\Models\Party;
use App\Models\Product;
use App\Models\Sale;
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
function stockSaleItem(int $productId, string $quantity): array
{
    return [
        'product_id' => $productId,
        'product_name' => '',
        'quantity' => $quantity,
        'unit_price' => '10.00',
        'discount_amount' => '',
        'tax_rate' => '',
    ];
}

function stockIn(Product $product, Warehouse $warehouse, float $quantity): void
{
    StockMovement::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'movement_type' => MovementType::IN->value,
        'quantity' => $quantity,
        'movement_date' => today(),
    ]);
}

test('a sale exceeding the available warehouse stock is rejected', function () {
    stockIn($this->product, $this->warehouse, 5);

    Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('partyId', $this->customer->id)
        ->set('warehouseId', $this->warehouse->id)
        ->set('cashboxId', $this->cashbox->id)
        ->set('paidAmount', '20.00')
        ->set('items', [stockSaleItem($this->product->id, '6')])
        ->call('save')
        ->assertHasErrors(['items.0.quantity']);

    expect(Sale::query()->count())->toBe(0)
        ->and(StockMovement::query()->where('movement_type', MovementType::OUT->value)->count())->toBe(0);
});

test('a sale within the available warehouse stock is saved', function () {
    stockIn($this->product, $this->warehouse, 5);

    Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('partyId', $this->customer->id)
        ->set('warehouseId', $this->warehouse->id)
        ->set('cashboxId', $this->cashbox->id)
        ->set('paidAmount', '20.00')
        ->set('items', [stockSaleItem($this->product->id, '2')])
        ->call('save')
        ->assertHasNoErrors();

    expect(Sale::query()->count())->toBe(1);
});

test('duplicate lines of the same product are checked together against stock', function () {
    stockIn($this->product, $this->warehouse, 5);

    Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('partyId', $this->customer->id)
        ->set('warehouseId', $this->warehouse->id)
        ->set('cashboxId', $this->cashbox->id)
        ->set('paidAmount', '20.00')
        ->set('items', [
            stockSaleItem($this->product->id, '3'),
            stockSaleItem($this->product->id, '3'),
        ])
        ->call('save')
        ->assertHasErrors(['items.0.quantity']);

    expect(Sale::query()->count())->toBe(0);
});

test('stock held in another warehouse does not count', function () {
    stockIn($this->product, Warehouse::factory()->create(), 50);

    Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('partyId', $this->customer->id)
        ->set('warehouseId', $this->warehouse->id)
        ->set('cashboxId', $this->cashbox->id)
        ->set('paidAmount', '20.00')
        ->set('items', [stockSaleItem($this->product->id, '2')])
        ->call('save')
        ->assertHasErrors(['items.0.quantity']);

    expect(Sale::query()->count())->toBe(0);
});

test('a draft sale is not checked against stock', function () {
    Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('partyId', $this->customer->id)
        ->set('warehouseId', $this->warehouse->id)
        ->set('isDraft', true)
        ->set('items', [stockSaleItem($this->product->id, '99')])
        ->call('save')
        ->assertHasNoErrors();

    expect(Sale::query()->sole()->status)->toBe(DocumentStatus::DRAFT);
});

test('sales in another warehouse do not consume this warehouse stock', function () {
    $other = Warehouse::factory()->create();

    stockIn($this->product, $this->warehouse, 5);
    stockIn($this->product, $other, 5);

    StockMovement::factory()->create([
        'product_id' => $this->product->id,
        'warehouse_id' => $other->id,
        'movement_type' => MovementType::OUT->value,
        'quantity' => 5,
        'movement_date' => today(),
    ]);

    Livewire::actingAs($this->user)
        ->test('livewire.sales.create')
        ->set('partyId', $this->customer->id)
        ->set('warehouseId', $this->warehouse->id)
        ->set('cashboxId', $this->cashbox->id)
        ->set('paidAmount', '20.00')
        ->set('items', [stockSaleItem($this->product->id, '5')])
        ->call('save')
        ->assertHasNoErrors();

    expect(Sale::query()->count())->toBe(1);
});
