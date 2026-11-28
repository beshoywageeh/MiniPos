<?php

use App\Enums\MovementType;
use App\Enums\StockAdjustmentReason;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function newAdjustmentPayload(array $overrides = []): array
{
    return array_merge([
        'productId' => Product::factory()->create()->id,
        'warehouseId' => Warehouse::factory()->create()->id,
        'movementType' => MovementType::IN->value,
        'quantity' => '2',
        'unitCost' => '1.50',
        'reason' => StockAdjustmentReason::CountCorrection->value,
        'note' => 'Counted twice.',
        'movementDate' => today()->toDateString(),
        'isOverride' => false,
    ], $overrides);
}

test('guests are redirected from the new adjustment form', function () {
    $this->get(route('stock-movements.create'))->assertRedirect(route('login'));
});

test('new adjustment form defaults dates and offers only active physical products', function () {
    $user = User::factory()->create();
    $active = Product::factory()->create(['name' => 'Active Physical Item']);
    Product::factory()->create(['name' => 'Retired Item', 'is_active' => false]);
    Product::factory()->service()->create(['name' => 'Installation Service']);
    Warehouse::factory()->create(['name' => 'North Warehouse']);

    $component = Livewire::actingAs($user)->test('livewire.stock-movements.create');

    expect($component->instance()->movementDate)->toBe(today()->format('Y-m-d'))
        ->and($component->instance()->movementType)->toBe(MovementType::IN->value);

    $component->assertSee('Active Physical Item')
        ->assertDontSee('Retired Item')
        ->assertDontSee('Installation Service')
        ->assertSee('North Warehouse');

    foreach (StockAdjustmentReason::cases() as $reason) {
        $component->assertSee($reason->label());
    }
});

test('empty new adjustment shows validation errors and saves nothing', function () {
    Livewire::actingAs(User::factory()->create())->test('livewire.stock-movements.create')
        ->set('productId', '')
        ->set('warehouseId', '')
        ->set('quantity', '')
        ->set('reason', '')
        ->call('save')
        ->assertHasErrors([
            'productId' => 'required',
            'warehouseId' => 'required',
            'quantity' => 'required',
            'reason' => 'required',
        ]);

    expect(StockAdjustment::query()->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(0);
});

test('saving an adjustment redirects to its printable preview', function () {
    $user = User::factory()->create();
    $payload = newAdjustmentPayload();

    $component = Livewire::actingAs($user)->test('livewire.stock-movements.create')
        ->set($payload)
        ->call('save')
        ->assertHasNoErrors();

    $adjustment = StockAdjustment::query()->sole();

    expect($adjustment->document_number)->toBe('STADJ-000001')
        ->and($adjustment->created_by)->toBe($user->id)
        ->and((float) $adjustment->unit_cost)->toBe(1.5)
        ->and($adjustment->reason)->toBe(StockAdjustmentReason::CountCorrection)
        ->and($adjustment->is_override)->toBeFalse()
        ->and($adjustment->movement->balance_after)->not->toBeNull()
        ->and($adjustment->movement->reference_type)->toBe(StockAdjustment::class)
        ->and($adjustment->movement->reference_id)->toBe($adjustment->id);

    $component->assertRedirect(route('stock-adjustments.preview', $adjustment));
});

test('adjustment document numbers use the stock adjustment sequence', function () {
    $user = User::factory()->create();

    foreach (range(1, 2) as $index) {
        Livewire::actingAs($user)->test('livewire.stock-movements.create')
            ->set(newAdjustmentPayload(['note' => 'Count '.$index.'.']))
            ->call('save')
            ->assertHasNoErrors();
    }

    expect(StockAdjustment::query()->orderBy('id')->pluck('document_number')->all())
        ->toBe(['STADJ-000001', 'STADJ-000002']);
});
