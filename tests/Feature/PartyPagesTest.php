<?php

use App\Enums\PartyType;
use App\Models\Brand;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('parties page route exists and requires auth', function () {
    expect(route('parties.index'))->toBe(url('/parties'));

    $this->get(route('parties.index'))->assertRedirect();
});

test('parties page lists parties with their type labels', function () {
    $user = User::factory()->create();
    Party::factory()->create(['name' => 'Acme Supplier', 'type' => PartyType::SUPPLIER]);

    Livewire::actingAs($user)->test('livewire.party.index')
        ->assertSee('Acme Supplier')
        ->assertSee('Supplier');
});

test('the parties list links each party to its detail page', function () {
    $user = User::factory()->create();
    $party = Party::factory()->create(['name' => 'Acme Supplier']);

    $html = Livewire::actingAs($user)->test('livewire.party.index')->html();

    expect($html)->toContain(route('parties.show', $party));
});

test('parties search matches the given field', function (string $value) {
    $user = User::factory()->create();
    Party::factory()->create([
        'name' => 'Acme',
        'code' => 'PTY-1111',
        'phone' => '555-0100',
    ]);
    Party::factory()->create([
        'name' => 'Other',
        'code' => 'PTY-2222',
        'phone' => '555-9999',
    ]);

    Livewire::actingAs($user)->test('livewire.party.index')
        ->set('search', $value)
        ->assertSee('Acme')
        ->assertDontSee('Other');
})->with([
    'name' => ['Acme'],
    'code' => ['PTY-1111'],
    'phone' => ['555-0100'],
]);

test('parties search with no match shows empty state', function () {
    $user = User::factory()->create();
    Party::factory()->create(['name' => 'Acme']);

    Livewire::actingAs($user)->test('livewire.party.index')
        ->set('search', 'zzzz')
        ->assertSee('No parties found.');
});

test('parties list refreshes when an event fires', function () {
    $user = User::factory()->create();
    $target = Party::factory()->create(['name' => 'Before Update']);

    $component = Livewire::actingAs($user)->test('livewire.party.index');

    expect($component->html())->toContain('Before Update');

    $target->update(['name' => 'After Update']);

    $component->dispatch('party-updated');

    expect($component->html())->toContain('After Update');
});

test('a party can be created with brands', function () {
    $user = User::factory()->create();
    $brands = Brand::factory()->count(2)->create();

    Livewire::actingAs($user)
        ->test('livewire.party.actions.create')
        ->set('code', 'PTY-TEST')
        ->set('name', 'Acme Supplier')
        ->set('phone', '555-0100')
        ->set('type', PartyType::SUPPLIER->value)
        ->set('brands', $brands->modelKeys())
        ->call('create')
        ->assertDispatched('party-created')
        ->assertHasNoErrors();

    $party = Party::where('code', 'PTY-TEST')->firstOrFail();

    expect($party->name)->toBe('Acme Supplier')
        ->and($party->phone)->toBe('555-0100')
        ->and($party->type)->toBe(PartyType::SUPPLIER)
        ->and($party->brands->modelKeys())->toEqualCanonicalizing($brands->modelKeys());
});

test('party creation validates required fields and duplicate code', function () {
    Party::factory()->create(['code' => 'PTY-TAKEN']);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.party.actions.create')
        ->set('code', 'PTY-TAKEN')
        ->set('name', '')
        ->set('phone', '')
        ->set('type', 'not-a-type')
        ->call('create')
        ->assertHasErrors([
            'code' => 'unique',
            'name' => 'required',
            'phone' => 'required',
            'type',
        ])
        ->assertNotDispatched('party-created');
});

test('party creation rejects an unknown brand', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.party.actions.create')
        ->set('code', 'PTY-BRAND')
        ->set('name', 'Acme')
        ->set('phone', '555-0100')
        ->set('brands', [999999])
        ->call('create')
        ->assertHasErrors(['brands.0' => 'exists'])
        ->assertNotDispatched('party-created');
});

test('a party can be updated including its brands', function () {
    $user = User::factory()->create();
    $party = Party::factory()->create(['name' => 'Old']);
    $oldBrand = Brand::factory()->create();
    $newBrands = Brand::factory()->count(2)->create();
    $party->brands()->attach($oldBrand);

    Livewire::actingAs($user)
        ->test('livewire.party.actions.update', ['party' => $party])
        ->assertSet('name', 'Old')
        ->set('name', 'New')
        ->set('brands', $newBrands->modelKeys())
        ->call('update')
        ->assertDispatched('party-updated')
        ->assertHasNoErrors();

    expect($party->refresh()->name)->toBe('New')
        ->and($party->brands->modelKeys())->toEqualCanonicalizing($newBrands->modelKeys());
});

test('keeping its own code passes unique validation on party update', function () {
    $user = User::factory()->create();
    $party = Party::factory()->create(['code' => 'PTY-KEEP']);

    Livewire::actingAs($user)
        ->test('livewire.party.actions.update', ['party' => $party])
        ->set('name', 'Renamed')
        ->call('update')
        ->assertHasNoErrors();

    expect($party->refresh()->code)->toBe('PTY-KEEP');
});

test('party update rejects a code used by another party', function () {
    Party::factory()->create(['code' => 'PTY-OTHER']);
    $party = Party::factory()->create();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.party.actions.update', ['party' => $party])
        ->set('code', 'PTY-OTHER')
        ->call('update')
        ->assertHasErrors(['code' => 'unique'])
        ->assertNotDispatched('party-updated');
});

test('a party can be soft deleted', function () {
    $user = User::factory()->create();
    $party = Party::factory()->create(['name' => 'Doomed']);

    Livewire::actingAs($user)
        ->test('livewire.party.actions.delete', ['party' => $party])
        ->call('delete')
        ->assertDispatched('party-deleted');

    expect($party->refresh()->deleted_at)->not->toBeNull()
        ->and(Party::where('name', 'Doomed')->exists())->toBeFalse();
});
