<?php

use App\Enums\MovementType;
use App\Enums\StockAdjustmentReason;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{adjustment: StockAdjustment, product: Product, warehouse: Warehouse, user: User}
 */
function previewAdjustment(): array
{
    $user = User::factory()->create(['name' => 'Stock Recorder']);
    $product = Product::factory()->create(['name' => 'Preview Widget']);
    $warehouse = Warehouse::factory()->create(['name' => 'Preview Warehouse']);

    $adjustment = StockAdjustment::factory()->create([
        'document_number' => 'STADJ-PREVIEW',
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'movement_type' => MovementType::OUT,
        'quantity' => 4,
        'unit_cost' => 2.5,
        'reason' => StockAdjustmentReason::Damage,
        'note' => 'Damaged in transit.',
        'is_override' => true,
        'created_by' => $user->id,
    ]);

    StockMovement::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'movement_type' => MovementType::OUT->value,
        'quantity' => 4,
        'unit_cost' => 2.5,
        'balance_after' => 9,
        'reference_type' => StockAdjustment::class,
        'reference_id' => $adjustment->id,
        'note' => 'Damaged in transit.',
        'is_override' => true,
        'movement_date' => $adjustment->movement_date,
        'created_by' => $user->id,
    ]);

    return ['adjustment' => $adjustment, 'product' => $product, 'warehouse' => $warehouse, 'user' => $user];
}

test('guests are redirected from the stock adjustment preview', function () {
    $adjustment = previewAdjustment()['adjustment'];

    $this->get(route('stock-adjustments.preview', $adjustment))
        ->assertRedirect(route('login'));
});

test('stock adjustment preview renders the saved document', function () {
    ['adjustment' => $adjustment, 'user' => $user] = previewAdjustment();

    Settings::set('company_name', 'Preview Store');
    Settings::set('company_address', '12 Stock Lane');
    Settings::set('company_phone', '555-1234');
    Settings::set('invoice_footer', 'Adjustment footer.');

    $this->actingAs($user)->get(route('stock-adjustments.preview', $adjustment))
        ->assertOk()
        ->assertSee('STADJ-PREVIEW')
        ->assertSee('Preview Widget')
        ->assertSee('Preview Warehouse')
        ->assertSee(__('stock_adjustments.movement_out'))
        ->assertSee(StockAdjustmentReason::Damage->label())
        ->assertSee('4.000')
        ->assertSee('2.50')
        ->assertSee('9.000')
        ->assertSee(__('stock_adjustments.override_yes'))
        ->assertSee('Damaged in transit.')
        ->assertSee('Stock Recorder')
        ->assertSee('Preview Store')
        ->assertSee('12 Stock Lane')
        ->assertSee('555-1234')
        ->assertSee('Adjustment footer.');
});

test('missing stock adjustment returns 404', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('stock-adjustments.preview', 999999))
        ->assertNotFound();
});
