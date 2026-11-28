<?php

use App\Enums\MovementType;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 */
function stockMovementForLedger(array $overrides = []): StockMovement
{
    return StockMovement::factory()->create(array_merge([
        'product_id' => Product::factory(),
        'warehouse_id' => Warehouse::factory(),
        'movement_type' => MovementType::IN->value,
        'quantity' => 1,
        'unit_cost' => 0,
        'balance_after' => 1,
        'note' => null,
        'movement_date' => '2026-11-20',
        'reference_type' => null,
        'reference_id' => null,
        'created_by' => User::factory(),
    ], $overrides));
}

test('guests are redirected from the movement ledger', function () {
    expect(route('stock-movements.index'))->toBe(url('/stock-movements'));

    $this->get(route('stock-movements.index'))->assertRedirect(route('login'));
});

test('ledger lists all movement columns', function () {
    $user = User::factory()->create(['name' => 'Ledger Recorder']);
    $product = Product::factory()->create(['name' => 'Ledger Widget', 'code' => 'LEDGER-1']);
    $warehouse = Warehouse::factory()->create(['name' => 'Ledger Warehouse']);

    stockMovementForLedger([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'movement_type' => MovementType::OUT->value,
        'quantity' => 3.5,
        'unit_cost' => 2.25,
        'balance_after' => 6.75,
        'note' => 'Counted short',
        'movement_date' => '2026-11-20',
        'created_by' => $user->id,
        'is_override' => true,
    ]);

    Livewire::actingAs($user)
        ->test('livewire.stock-movements.index')
        ->assertSee('2026-11-20')
        ->assertSee('Ledger Widget')
        ->assertSee('LEDGER-1')
        ->assertSee('Ledger Warehouse')
        ->assertSee(__('stock_movements.out'))
        ->assertSee('3.500')
        ->assertSee('2.25')
        ->assertSee('6.750')
        ->assertSee('Ledger Recorder')
        ->assertSee('Counted short')
        ->assertSee(__('stock_movements.override'));
});

test('product and code search filters movements', function (string $search) {
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Searchable Item', 'code' => 'SEARCH-777']);
    $other = Product::factory()->create(['name' => 'Hidden Item', 'code' => 'HIDDEN-888']);

    stockMovementForLedger(['product_id' => $product->id, 'created_by' => $user->id]);
    stockMovementForLedger(['product_id' => $other->id, 'created_by' => $user->id]);

    Livewire::actingAs($user)
        ->test('livewire.stock-movements.index')
        ->set('search', $search)
        ->assertSee('Searchable Item')
        ->assertDontSee('Hidden Item');
})->with([
    'name' => ['Searchable Item'],
    'code' => ['SEARCH-777'],
]);

test('warehouse filter shows only that warehouse', function () {
    $user = User::factory()->create();
    $central = Warehouse::factory()->create(['name' => 'Central Ledger Warehouse']);
    $north = Warehouse::factory()->create(['name' => 'North Ledger Warehouse']);
    $centralProduct = Product::factory()->create(['name' => 'Central warehouse item']);
    $northProduct = Product::factory()->create(['name' => 'North warehouse item']);

    stockMovementForLedger([
        'warehouse_id' => $central->id,
        'product_id' => $centralProduct->id,
        'created_by' => $user->id,
    ]);
    stockMovementForLedger([
        'warehouse_id' => $north->id,
        'product_id' => $northProduct->id,
        'created_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('livewire.stock-movements.index')
        ->set('warehouse', (string) $central->id)
        ->assertSee('Central Ledger Warehouse')
        ->assertSee('Central warehouse item')
        ->assertDontSee('North warehouse item');
});

test('direction filter shows only the selected direction', function () {
    $user = User::factory()->create();
    $inProduct = Product::factory()->create(['name' => 'Inbound Ledger Item']);
    $outProduct = Product::factory()->create(['name' => 'Outbound Ledger Item']);

    stockMovementForLedger([
        'product_id' => $inProduct->id,
        'movement_type' => MovementType::IN->value,
        'created_by' => $user->id,
    ]);
    stockMovementForLedger([
        'product_id' => $outProduct->id,
        'movement_type' => MovementType::OUT->value,
        'created_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('livewire.stock-movements.index')
        ->set('movementType', MovementType::OUT->value)
        ->assertSee('Outbound Ledger Item')
        ->assertDontSee('Inbound Ledger Item');
});

test('date range filter includes and excludes movements', function () {
    $user = User::factory()->create();

    stockMovementForLedger(['note' => 'Inside ledger range', 'movement_date' => '2026-11-15', 'created_by' => $user->id]);
    stockMovementForLedger(['note' => 'Before ledger range', 'movement_date' => '2026-11-01', 'created_by' => $user->id]);
    stockMovementForLedger(['note' => 'After ledger range', 'movement_date' => '2026-11-30', 'created_by' => $user->id]);

    Livewire::actingAs($user)
        ->test('livewire.stock-movements.index')
        ->set('dateFrom', '2026-11-10')
        ->set('dateTo', '2026-11-20')
        ->assertSee('Inside ledger range')
        ->assertDontSee('Before ledger range')
        ->assertDontSee('After ledger range');
});

test('ledger paginates at ten and orders newest first', function () {
    $user = User::factory()->create();

    foreach (range(0, 10) as $index) {
        stockMovementForLedger([
            'note' => sprintf('Ledger movement %02d', $index),
            'movement_date' => now()->subDays(10 - $index)->toDateString(),
            'created_by' => $user->id,
        ]);
    }

    $component = Livewire::actingAs($user)->test('livewire.stock-movements.index');

    $component
        ->assertSeeHtmlInOrder(['Ledger movement 10', 'Ledger movement 09'])
        ->assertDontSee('Ledger movement 00');

    $component->call('gotoPage', 2)
        ->assertSee('Ledger movement 00');
});

test('sale and purchase sources link to invoice previews', function () {
    $user = User::factory()->create();
    $sale = Sale::factory()->create(['document_number' => 'SALE-LEDGER-1']);
    $purchase = Purchase::factory()->create(['document_number' => 'PUR-LEDGER-1']);

    stockMovementForLedger([
        'reference_type' => Sale::class,
        'reference_id' => $sale->id,
        'created_by' => $user->id,
    ]);
    stockMovementForLedger([
        'reference_type' => Purchase::class,
        'reference_id' => $purchase->id,
        'created_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('livewire.stock-movements.index')
        ->assertSee('SALE-LEDGER-1')
        ->assertSee('PUR-LEDGER-1')
        ->assertSeeHtml('href="'.route('sales.invoice_preview', $sale).'"')
        ->assertSeeHtml('href="'.route('purchases.invoice_preview', $purchase).'"');
});

test('adjustment source links to the printable preview', function () {
    $user = User::factory()->create();
    $adjustment = StockAdjustment::factory()->create(['document_number' => 'STADJ-LEDGER-1']);

    stockMovementForLedger([
        'reference_type' => StockAdjustment::class,
        'reference_id' => $adjustment->id,
        'created_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('livewire.stock-movements.index')
        ->assertSee('STADJ-LEDGER-1')
        ->assertSeeHtml('href="'.route('stock-adjustments.preview', $adjustment).'"');
});

test('legacy and unknown sources render safe labels', function () {
    $user = User::factory()->create();

    stockMovementForLedger([
        'reference_type' => 'adjustment',
        'reference_id' => 99,
        'note' => 'Legacy ledger movement',
        'created_by' => $user->id,
    ]);
    stockMovementForLedger([
        'reference_type' => null,
        'note' => 'Unlinked ledger movement',
        'created_by' => $user->id,
    ]);
    stockMovementForLedger([
        'reference_type' => 'App\\Models\\MissingLedgerSource',
        'reference_id' => 1,
        'note' => 'Unknown ledger movement',
        'created_by' => $user->id,
    ]);
    stockMovementForLedger([
        'reference_type' => SalesReturn::class,
        'reference_id' => 1,
        'created_by' => $user->id,
    ]);
    stockMovementForLedger([
        'reference_type' => PurchaseReturn::class,
        'reference_id' => 1,
        'created_by' => $user->id,
    ]);
    stockMovementForLedger([
        'reference_type' => Sale::class,
        'reference_id' => 999999,
        'created_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('livewire.stock-movements.index')
        ->assertSee('Legacy ledger movement')
        ->assertSee('Unlinked ledger movement')
        ->assertSee('Unknown ledger movement')
        ->assertSee(__('stock_movements.source_legacy_adjustment'))
        ->assertSee(__('stock_movements.source_sales_return'))
        ->assertSee(__('stock_movements.source_purchase_return'))
        ->assertSee(__('stock_movements.source_sale'))
        ->assertSee('—')
        ->assertDontSeeHtml('href="'.url('/sales/999999/invoice-preview').'"');
});

test('null balances render a placeholder', function () {
    $user = User::factory()->create();
    $adjustment = StockAdjustment::factory()->create(['document_number' => 'STADJ-NULL-BALANCE']);

    stockMovementForLedger([
        'reference_type' => StockAdjustment::class,
        'reference_id' => $adjustment->id,
        'balance_after' => null,
        'note' => 'Null balance ledger movement',
        'created_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('livewire.stock-movements.index')
        ->assertSee('Null balance ledger movement')
        ->assertSee('STADJ-NULL-BALANCE')
        ->assertSee('—');
});

test('user-supplied text is escaped', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => '<script>alert(1)</script>']);

    stockMovementForLedger([
        'product_id' => $product->id,
        'note' => '<script>alert(1)</script>',
        'created_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('livewire.stock-movements.index')
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertSeeHtml('&lt;script&gt;alert(1)&lt;/script&gt;');
});
