<?php

use App\Enums\DocumentStatus;
use App\Models\Party;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('purchases index route exists and requires auth', function () {
    expect(route('purchases.index'))->toBe(url('/purchases'));

    $this->get(route('purchases.index'))->assertRedirect();
});

test('purchases index lists purchases with supplier warehouse totals and status', function () {
    $user = User::factory()->create();
    $supplier = Party::factory()->supplier()->create(['name' => 'Acme Supplier']);
    $warehouse = Warehouse::factory()->create(['name' => 'Main Store']);
    $purchase = Purchase::factory()->for($supplier, 'party')->for($warehouse)->create([
        'document_number' => 'PUR-LIST',
        'status' => DocumentStatus::PAID,
    ]);

    $html = Livewire::actingAs($user)->test('livewire.purchase.index')
        ->assertSee('PUR-LIST')
        ->assertSee('Acme Supplier')
        ->assertSee('Main Store')
        ->html();

    expect($html)->toContain(route('purchases.invoice_preview', $purchase));
});

test('purchases search matches document number and supplier name', function (string $value) {
    $user = User::factory()->create();
    Purchase::factory()->create(['document_number' => 'PUR-AAA']);
    Purchase::factory()->for(Party::factory()->supplier()->create(['name' => 'Beta Supplier']), 'party')->create();

    Livewire::actingAs($user)->test('livewire.purchase.index')
        ->set('search', $value)
        ->assertSee($value);
})->with(['PUR-AAA', 'Beta Supplier']);

test('purchases warehouse filter limits results', function () {
    $user = User::factory()->create();
    $first = Warehouse::factory()->create(['name' => 'First Depot']);
    $second = Warehouse::factory()->create(['name' => 'Second Depot']);
    Purchase::factory()->for($first)->create(['document_number' => 'PUR-FIRST']);
    Purchase::factory()->for($second)->create(['document_number' => 'PUR-SECOND']);

    Livewire::actingAs($user)->test('livewire.purchase.index')
        ->set('warehouse', (string) $second->id)
        ->assertSee('PUR-SECOND')
        ->assertDontSee('PUR-FIRST');
});

test('purchases index paginates at ten newest first', function () {
    $user = User::factory()->create();
    foreach (range(1, 12) as $number) {
        Purchase::factory()->create([
            'document_number' => sprintf('PUR-%02d', $number),
            'invoice_date' => '2026-01-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
        ]);
    }

    $component = Livewire::actingAs($user)->test('livewire.purchase.index');

    expect($component->html())->toContain('PUR-12')->not->toContain('PUR-01');

    $component->call('gotoPage', 2);

    expect($component->html())->toContain('PUR-01')->not->toContain('PUR-12');
});

test('purchases index escapes supplier names', function () {
    $user = User::factory()->create();
    $supplier = Party::factory()->supplier()->create(['name' => '<script>alert(1)</script>']);
    Purchase::factory()->for($supplier, 'party')->create();

    $html = Livewire::actingAs($user)->test('livewire.purchase.index')->html();

    expect($html)->not->toContain('<script>alert(1)</script>')->toContain('&lt;script&gt;');
});
