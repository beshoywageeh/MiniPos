<?php

use App\Enums\ResetPeriod;
use App\Models\DocumentSequence;
use App\Models\User;
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
