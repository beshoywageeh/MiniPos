<?php

use App\Models\Party;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('sales page route exists and requires auth', function () {
    expect(route('sales.index'))->toBe(url('/sales'));

    $this->get(route('sales.index'))->assertRedirect(route('login'));
});

test('sales list shows the document number, customer, warehouse and total', function () {
    $user = User::factory()->create();
    $customer = Party::factory()->customer()->create(['name' => 'Acme Supplies']);
    $warehouse = Warehouse::factory()->create(['name' => 'Central Warehouse']);

    Sale::factory()->create([
        'party_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'document_number' => 'SALE-900001',
        'total_amount' => 123.45,
    ]);

    Livewire::actingAs($user)
        ->test('livewire.sales.index')
        ->assertSee('SALE-900001')
        ->assertSee('Acme Supplies')
        ->assertSee('Central Warehouse')
        ->assertSee('123.45');
});

test('sales list shows the walk-in label when there is no customer', function () {
    $user = User::factory()->create();
    Sale::factory()->create(['party_id' => null, 'document_number' => 'SALE-900002']);

    Livewire::actingAs($user)
        ->test('livewire.sales.index')
        ->assertSee('SALE-900002')
        ->assertSee(__('sales.no_customer'));
});

test('sales list can be filtered by warehouse', function () {
    $user = User::factory()->create();
    $central = Warehouse::factory()->create();
    $north = Warehouse::factory()->create();

    Sale::factory()->create([
        'warehouse_id' => $central->id,
        'document_number' => 'SALE-CENTRAL',
    ]);
    Sale::factory()->create([
        'warehouse_id' => $north->id,
        'document_number' => 'SALE-NORTH',
    ]);

    Livewire::actingAs($user)
        ->test('livewire.sales.index')
        ->set('warehouse', (string) $central->id)
        ->assertSee('SALE-CENTRAL')
        ->assertDontSee('SALE-NORTH');
});

test('sales search matches the document number and the customer name', function (string $value) {
    $user = User::factory()->create();
    $customer = Party::factory()->customer()->create(['name' => 'Acme Supplies']);

    Sale::factory()->create([
        'party_id' => $customer->id,
        'document_number' => 'SALE-111111',
    ]);
    Sale::factory()->create([
        'party_id' => Party::factory()->customer()->create(['name' => 'Other Buyer'])->id,
        'document_number' => 'SALE-222222',
    ]);

    Livewire::actingAs($user)
        ->test('livewire.sales.index')
        ->set('search', $value)
        ->assertSee('SALE-111111')
        ->assertDontSee('SALE-222222');
})->with([
    'document number' => ['SALE-111111'],
    'customer name' => ['Acme Supplies'],
]);

test('sales list with no records shows the empty state', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.sales.index')
        ->assertSee(__('sales.no_records'));
});
