<?php

use App\Enums\DocumentStatus;
use App\Enums\ResetPeriod;
use App\Models\DocumentSequence;
use App\Models\Party;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('document sequences route exists and requires auth', function () {
    expect(route('document-sequences.index'))->toBe(url('/document-sequences'));

    $this->get(route('document-sequences.index'))->assertRedirect();
});

test('the sidebar links to the document sequences page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('document-sequences.index'));
});

test('the index lists sequences with their preview', function () {
    DocumentSequence::factory()->forDocumentType('sale', 'SALE')->create([
        'next_number' => 7,
        'padding' => 4,
        'reset_period' => ResetPeriod::MONTHLY,
        'last_reset_at' => '2026-11-01 00:00:00',
    ]);
    DocumentSequence::factory()->forDocumentType('expense', 'EXP')->create([
        'next_number' => 3,
        'padding' => 6,
        'reset_period' => ResetPeriod::NEVER,
    ]);

    $user = User::factory()->create();

    Livewire::actingAs($user)->test('livewire.document-sequences.index')
        ->assertSee('Sale')
        ->assertSee('SALE-0007')
        ->assertSee('Monthly')
        ->assertSee('Expense')
        ->assertSee('EXP-000003')
        ->assertSee('Never');
});

test('a sequence can be updated', function () {
    $sequence = DocumentSequence::factory()->forDocumentType('sale', 'SALE')->create([
        'next_number' => 7,
        'padding' => 6,
        'reset_period' => ResetPeriod::MONTHLY,
    ]);

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.document-sequences.actions.edit', ['sequence' => $sequence])
        ->assertSet('prefix', 'SALE')
        ->set('prefix', 'INV')
        ->set('nextNumber', '20')
        ->set('padding', '4')
        ->set('resetPeriod', ResetPeriod::YEARLY->value)
        ->call('update')
        ->assertDispatched('document-sequence-updated')
        ->assertHasNoErrors();

    expect($sequence->refresh())
        ->prefix->toBe('INV')
        ->next_number->toBe(20)
        ->padding->toBe(4)
        ->reset_period->toBe(ResetPeriod::YEARLY);
});

test('editing a sequence validates prefix, padding and reset period', function () {
    $sequence = DocumentSequence::factory()->forDocumentType('sale', 'SALE')->create();

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.document-sequences.actions.edit', ['sequence' => $sequence])
        ->set('prefix', '')
        ->set('padding', '11')
        ->set('resetPeriod', 'hourly')
        ->call('update')
        ->assertHasErrors([
            'prefix' => 'required',
            'padding' => 'max',
            'resetPeriod' => 'in',
        ])
        ->assertNotDispatched('document-sequence-updated');
});

test('a sale accepts a document number through mass assignment', function () {
    $sale = Sale::create([
        'document_number' => 'SALE-000042',
        'party_id' => Party::factory()->customer()->create()->id,
        'warehouse_id' => Warehouse::factory()->create()->id,
        'invoice_date' => now(),
        'subtotal' => 10,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 10,
        'paid_amount' => 10,
        'remaining_amount' => 0,
        'status' => DocumentStatus::PAID,
        'created_by' => User::factory()->create()->id,
    ]);

    expect($sale->refresh()->document_number)->toBe('SALE-000042');
});
