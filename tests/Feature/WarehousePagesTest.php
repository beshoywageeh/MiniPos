<?php

use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('warehouses page route exists and requires auth', function () {
    expect(route('warehouses.index'))->toBe(url('/warehouses'));

    $this->get(route('warehouses.index'))->assertRedirect(route('login'));
});

test('warehouses page lists warehouses for authenticated users', function () {
    $user = User::factory()->create();
    Warehouse::factory()->create([
        'code' => 'WH-001',
        'name' => 'Main Warehouse',
        'address' => '1 Market Street',
    ]);

    Livewire::actingAs($user)
        ->test('livewire.warehouse.index')
        ->assertSee('WH-001')
        ->assertSee('Main Warehouse')
        ->assertSee('1 Market Street');
});

test('warehouses search matches the given field', function (string $value) {
    $user = User::factory()->create();
    Warehouse::factory()->create([
        'code' => 'WH-1111',
        'name' => 'Central Depot',
        'address' => '12 Harbour Road',
    ]);
    Warehouse::factory()->create([
        'code' => 'WH-2222',
        'name' => 'North Depot',
        'address' => '99 Mountain Lane',
    ]);

    Livewire::actingAs($user)
        ->test('livewire.warehouse.index')
        ->set('search', $value)
        ->assertSee('Central Depot')
        ->assertDontSee('North Depot');
})->with([
    'code' => ['WH-1111'],
    'name' => ['Central'],
    'address' => ['Harbour'],
]);

test('warehouses search with no match shows the empty state', function () {
    $user = User::factory()->create();
    Warehouse::factory()->create(['name' => 'Central Depot']);

    Livewire::actingAs($user)
        ->test('livewire.warehouse.index')
        ->set('search', 'zzzz')
        ->assertSee(__('warehouses.no_records'));
});

test('warehouse list refreshes when an event fires', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create(['name' => 'Before Update']);

    $component = Livewire::actingAs($user)->test('livewire.warehouse.index');

    expect($component->html())->toContain('Before Update');

    $warehouse->update(['name' => 'After Update']);

    $component->dispatch('warehouse-updated');

    expect($component->html())->toContain('After Update');
});

test('a warehouse can be created', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.warehouse.actions.create')
        ->set('code', 'WH-TEST')
        ->set('name', 'Test Warehouse')
        ->set('address', '5 Test Avenue')
        ->set('note', 'Created by test')
        ->set('isActive', false)
        ->call('create')
        ->assertDispatched('warehouse-created')
        ->assertHasNoErrors();

    $warehouse = Warehouse::where('code', 'WH-TEST')->firstOrFail();

    expect($warehouse->name)->toBe('Test Warehouse')
        ->and($warehouse->address)->toBe('5 Test Avenue')
        ->and($warehouse->note)->toBe('Created by test')
        ->and($warehouse->is_active)->toBeFalse();
});

test('warehouse creation validates required fields and duplicate code', function () {
    Warehouse::factory()->create(['code' => 'WH-TAKEN']);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.warehouse.actions.create')
        ->set('code', 'WH-TAKEN')
        ->set('name', '')
        ->call('create')
        ->assertHasErrors([
            'code' => 'unique',
            'name' => 'required',
        ])
        ->assertNotDispatched('warehouse-created');
});

test('a warehouse can be updated', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create(['name' => 'Old Name']);

    Livewire::actingAs($user)
        ->test('livewire.warehouse.actions.update', ['warehouse' => $warehouse])
        ->assertSet('name', 'Old Name')
        ->set('name', 'New Name')
        ->set('address', '9 New Street')
        ->call('update')
        ->assertDispatched('warehouse-updated')
        ->assertHasNoErrors();

    expect($warehouse->refresh()->name)->toBe('New Name')
        ->and($warehouse->address)->toBe('9 New Street');
});

test('keeping its own code passes unique validation on warehouse update', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create(['code' => 'WH-KEEP']);

    Livewire::actingAs($user)
        ->test('livewire.warehouse.actions.update', ['warehouse' => $warehouse])
        ->set('name', 'Renamed Warehouse')
        ->call('update')
        ->assertHasNoErrors();

    expect($warehouse->refresh()->code)->toBe('WH-KEEP');
});

test('warehouse update rejects a code used by another warehouse', function () {
    Warehouse::factory()->create(['code' => 'WH-OTHER']);
    $warehouse = Warehouse::factory()->create();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.warehouse.actions.update', ['warehouse' => $warehouse])
        ->set('code', 'WH-OTHER')
        ->call('update')
        ->assertHasErrors(['code' => 'unique'])
        ->assertNotDispatched('warehouse-updated');
});

test('a warehouse can be soft deleted', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create(['name' => 'Doomed Warehouse']);

    Livewire::actingAs($user)
        ->test('livewire.warehouse.actions.delete', ['warehouse' => $warehouse])
        ->call('delete')
        ->assertDispatched('warehouse-deleted');

    expect($warehouse->refresh()->deleted_at)->not->toBeNull()
        ->and(Warehouse::where('name', 'Doomed Warehouse')->exists())->toBeFalse();
});

test('warehouse list shows a sales count linking to the filtered sales list', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create(['name' => 'Central Warehouse']);
    Sale::factory()->count(2)->create(['warehouse_id' => $warehouse->id]);

    $html = Livewire::actingAs($user)
        ->test('livewire.warehouse.index')
        ->html();

    expect($html)->toContain(route('sales.index', ['warehouse' => $warehouse->id]))
        ->and($html)->toMatch('/warehouse='.$warehouse->id.'"[^>]*>\s*2\s*<\/a>/');
});
