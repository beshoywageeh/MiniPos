<?php

use App\Enums\DocumentStatus;
use App\Enums\MovementType;
use App\Enums\PaymentType;
use App\Models\Cashbox;
use App\Models\DocumentSequence;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    DocumentSequence::factory()->forDocumentType('purchase', 'PUR')->create([
        'next_number' => 1,
        'padding' => 6,
    ]);
    DocumentSequence::factory()->forDocumentType('payment', 'PAY')->create([
        'next_number' => 1,
        'padding' => 6,
    ]);
});

test('a purchase can be saved with draft status', function () {
    $purchase = Purchase::factory()->create(['status' => DocumentStatus::DRAFT]);

    expect($purchase->fresh()->status)->toBe(DocumentStatus::DRAFT);
});

test('existing purchase statuses remain valid after the migration', function () {
    foreach ([DocumentStatus::PENDING, DocumentStatus::PARTIAL, DocumentStatus::PAID] as $status) {
        expect(Purchase::factory()->create(['status' => $status])->fresh()->status)->toBe($status);
    }
});

test('guests are redirected from the new purchase form', function () {
    $this->get(route('purchases.create'))->assertRedirect(route('login'));
});

test('new purchase form defaults dates and preselects the sole warehouse and cashbox', function () {
    $user = User::factory()->create();
    Party::factory()->supplier()->create(['name' => 'Acme Supplier']);
    Party::factory()->customer()->create(['name' => 'Walk-in Customer']);
    $warehouse = Warehouse::factory()->create();
    $cashbox = Cashbox::factory()->create();

    $component = Livewire::actingAs($user)->test('livewire.purchase.create');

    expect($component->instance()->invoiceDate)->toBe(today()->format('Y-m-d'))
        ->and($component->instance()->dueDate)->toBe(today()->addDays(14)->format('Y-m-d'))
        ->and($component->instance()->warehouseId)->toBe($warehouse->id)
        ->and($component->instance()->cashboxId)->toBe($cashbox->id);

    $component->assertSee('Acme Supplier')->assertDontSee('Walk-in Customer');
});

test('empty new purchase shows validation errors and saves nothing', function () {
    Livewire::actingAs(User::factory()->create())->test('livewire.purchase.create')
        ->set('partyId', '')
        ->set('warehouseId', '')
        ->set('items.0.product_id', '')
        ->call('save')
        ->assertHasErrors(['partyId' => 'required', 'warehouseId' => 'required', 'items.0.product_id' => 'required']);

    expect(Purchase::query()->count())->toBe(0);
});

test('paid amount above grand total is rejected', function () {
    $product = Product::factory()->create(['purchase_price' => 100]);
    Livewire::actingAs(User::factory()->create())->test('livewire.purchase.create')
        ->set('partyId', Party::factory()->supplier()->create()->id)
        ->set('warehouseId', Warehouse::factory()->create()->id)
        ->set('cashboxId', Cashbox::factory()->create()->id)
        ->set('items.0.product_id', $product->id)
        ->set('items.0.quantity', 1)
        ->set('items.0.unit_price', 100)
        ->set('paidAmount', 101)
        ->call('save')
        ->assertHasErrors('paidAmount');

    expect(Purchase::query()->count())->toBe(0);
});

test('draft purchase ignores paid amount and creates no stock or payment', function () {
    $user = User::factory()->create();
    $supplier = Party::factory()->supplier()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create(['purchase_price' => 40]);
    Cashbox::factory()->create();

    Livewire::actingAs($user)->test('livewire.purchase.create')
        ->set('partyId', $supplier->id)
        ->set('warehouseId', $warehouse->id)
        ->set('items.0.product_id', $product->id)
        ->set('items.0.quantity', 2)
        ->set('items.0.unit_price', 40)
        ->set('paidAmount', 80)
        ->set('isDraft', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('purchases.invoice_preview', Purchase::query()->firstOrFail()));

    $purchase = Purchase::query()->firstOrFail();

    expect($purchase->status)->toBe(DocumentStatus::DRAFT)
        ->and((float) $purchase->paid_amount)->toBe(0.0)
        ->and($purchase->items)->toHaveCount(1)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0);
});

test('non draft purchase records stock received and supplier payment', function () {
    $user = User::factory()->create();
    $supplier = Party::factory()->supplier()->create();
    $warehouse = Warehouse::factory()->create();
    $cashbox = Cashbox::factory()->create();
    $product = Product::factory()->create(['purchase_price' => 25]);

    Livewire::actingAs($user)->test('livewire.purchase.create')
        ->set('partyId', $supplier->id)
        ->set('warehouseId', $warehouse->id)
        ->set('items.0.product_id', $product->id)
        ->set('items.0.quantity', 3)
        ->set('items.0.unit_price', 25)
        ->set('paidAmount', 30)
        ->call('save')
        ->assertHasNoErrors();

    $purchase = Purchase::query()->firstOrFail();
    $movement = StockMovement::query()->firstOrFail();
    $payment = Payment::query()->firstOrFail();

    expect($purchase->status)->toBe(DocumentStatus::PARTIAL)
        ->and($purchase->document_number)->toBe('PUR-000001')
        ->and((float) $purchase->total_amount)->toBe(75.0)
        ->and($movement->movement_type)->toBe(MovementType::IN->value)
        ->and((float) $movement->unit_cost)->toBe(25.0)
        ->and($movement->reference_type)->toBe(Purchase::class)
        ->and($movement->reference_id)->toBe($purchase->id)
        ->and($payment->type)->toBe(PaymentType::payment)
        ->and($payment->purchase_id)->toBe($purchase->id)
        ->and($payment->party_id)->toBe($supplier->id)
        ->and($payment->document_number)->toBe('PAY-000001');
});

test('purchase status is paid or pending based on payment', function (float $paid, DocumentStatus $expected) {
    $user = User::factory()->create();
    $supplier = Party::factory()->supplier()->create();
    $warehouse = Warehouse::factory()->create();
    Cashbox::factory()->create();
    $product = Product::factory()->create(['purchase_price' => 50]);

    Livewire::actingAs($user)->test('livewire.purchase.create')
        ->set('partyId', $supplier->id)
        ->set('warehouseId', $warehouse->id)
        ->set('items.0.product_id', $product->id)
        ->set('items.0.quantity', 1)
        ->set('items.0.unit_price', 50)
        ->set('paidAmount', $paid)
        ->call('save')
        ->assertHasNoErrors();

    expect(Purchase::query()->firstOrFail()->status)->toBe($expected);
})->with([
    'unpaid' => [0, DocumentStatus::PENDING],
    'fully paid' => [50, DocumentStatus::PAID],
]);

test('service product lines are saved without stock movements', function () {
    $user = User::factory()->create();
    $supplier = Party::factory()->supplier()->create();
    $warehouse = Warehouse::factory()->create();
    $service = Product::factory()->service()->create(['purchase_price' => 90]);

    Livewire::actingAs($user)->test('livewire.purchase.create')
        ->set('partyId', $supplier->id)
        ->set('warehouseId', $warehouse->id)
        ->set('items.0.product_id', $service->id)
        ->set('items.0.quantity', 1)
        ->set('items.0.unit_price', 90)
        ->call('save')
        ->assertHasNoErrors();

    expect(Purchase::query()->firstOrFail()->items)->toHaveCount(1)
        ->and(StockMovement::query()->count())->toBe(0);
});

test('inactive products cannot be added from search', function () {
    $product = Product::factory()->create(['name' => 'Retired Widget', 'is_active' => false]);

    Livewire::actingAs(User::factory()->create())->test('livewire.purchase.create')
        ->set('searchTerm', 'Retired Widget')
        ->assertSet('searchResults', [])
        ->call('addProductFromSearch', $product->id)
        ->assertSet('items.0.product_id', null);
});

test('clearing the due date saves a purchase with a null due date', function () {
    $product = Product::factory()->create(['purchase_price' => 20]);

    Livewire::actingAs(User::factory()->create())->test('livewire.purchase.create')
        ->set('partyId', Party::factory()->supplier()->create()->id)
        ->set('warehouseId', Warehouse::factory()->create()->id)
        ->set('items.0.product_id', $product->id)
        ->set('items.0.quantity', 1)
        ->set('items.0.unit_price', 20)
        ->set('dueDate', '')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('purchases.invoice_preview', Purchase::query()->firstOrFail()));

    expect(Purchase::query()->firstOrFail()->due_date)->toBeNull();
});

test('inactive or wrong type records cannot be saved through the create form', function () {
    $inactiveSupplier = Party::factory()->supplier()->create(['is_active' => false]);
    $customer = Party::factory()->customer()->create();
    $activeSupplier = Party::factory()->supplier()->create();
    $activeWarehouse = Warehouse::factory()->create();
    $inactiveWarehouse = Warehouse::factory()->create(['is_active' => false]);
    $activeCashbox = Cashbox::factory()->create();
    $inactiveCashbox = Cashbox::factory()->create(['is_active' => false]);
    $activeProduct = Product::factory()->create(['purchase_price' => 10]);
    $inactiveProduct = Product::factory()->create(['purchase_price' => 10, 'is_active' => false]);

    Livewire::actingAs(User::factory()->create())->test('livewire.purchase.create')
        ->set('partyId', $inactiveSupplier->id)
        ->set('warehouseId', $inactiveWarehouse->id)
        ->set('cashboxId', $inactiveCashbox->id)
        ->set('paidAmount', 10)
        ->set('items.0.product_id', $inactiveProduct->id)
        ->set('items.0.quantity', 1)
        ->set('items.0.unit_price', 10)
        ->call('save')
        ->assertHasErrors([
            'partyId' => 'exists',
            'warehouseId' => 'exists',
            'cashboxId' => 'exists',
            'items.0.product_id' => 'exists',
        ]);

    expect(Purchase::query()->count())->toBe(0);

    Livewire::actingAs(User::factory()->create())->test('livewire.purchase.create')
        ->set('partyId', $customer->id)
        ->set('warehouseId', $activeWarehouse->id)
        ->set('cashboxId', $activeCashbox->id)
        ->set('paidAmount', 10)
        ->set('items.0.product_id', $activeProduct->id)
        ->set('items.0.quantity', 1)
        ->set('items.0.unit_price', 10)
        ->call('save')
        ->assertHasErrors(['partyId' => 'exists']);
});

test('rolling back the draft status migration does not strand draft purchases', function () {
    $purchase = Purchase::factory()->create(['status' => DocumentStatus::DRAFT]);
    $migration = require database_path('migrations/2026_11_27_000000_add_draft_to_purchases_status.php');

    $migration->down();

    expect(Purchase::query()->findOrFail($purchase->id)->status)->toBe(DocumentStatus::PENDING);
});
