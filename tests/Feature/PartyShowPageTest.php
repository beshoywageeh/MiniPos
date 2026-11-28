<?php

use App\Enums\DocumentStatus;
use App\Enums\PaymentType;
use App\Models\Brand;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('party show page route exists and requires auth', function () {
    $party = Party::factory()->create();

    expect(route('parties.show', $party))->toBe(url('/parties/'.$party->id));

    $this->get(route('parties.show', $party))->assertRedirect();
});

test('party show page renders all party details and brands', function () {
    $user = User::factory()->create();
    $brand = Brand::factory()->create(['name' => 'Acme Roasters']);
    $party = Party::factory()->customer()->create([
        'name' => 'Espresso Shop',
        'code' => 'PTY-SHOW',
        'phone' => '555-0100',
        'address' => '12 Main Street',
        'note' => 'Prefers email',
    ]);
    $party->brands()->attach($brand);

    Livewire::actingAs($user)->test('livewire.party.show', ['party' => $party])
        ->assertSee('Espresso Shop')
        ->assertSee('PTY-SHOW')
        ->assertSee('555-0100')
        ->assertSee('12 Main Street')
        ->assertSee('Prefers email')
        ->assertSee('Customer')
        ->assertSee('Acme Roasters');
});

test('party show page summarises sales purchases payments and net balance', function () {
    $user = User::factory()->create();
    $party = Party::factory()->create();

    Sale::factory()->for($party, 'party')->create(['total_amount' => 100]);
    SalesReturn::factory()->for($party, 'party')->create(['total_amount' => 10]);
    Purchase::factory()->for($party, 'party')->create(['total_amount' => 200]);
    PurchaseReturn::factory()->for($party, 'party')->create(['total_amount' => 20]);
    Payment::factory()->for($party, 'party')->create([
        'type' => PaymentType::recipt,
        'amount' => 30,
    ]);
    Payment::factory()->for($party, 'party')->create([
        'type' => PaymentType::payment,
        'amount' => 40,
    ]);

    $component = Livewire::actingAs($user)->test('livewire.party.show', ['party' => $party]);

    expect($component->html())
        ->toContain('100.00')
        ->toContain('200.00')
        ->toContain('70.00')
        ->toContain('80.00')
        ->toContain('You owe party');
});

test('party show page lists all five transaction types', function () {
    $user = User::factory()->create();
    $party = Party::factory()->create();
    $sale = Sale::factory()->for($party, 'party')->create([
        'document_number' => 'SALE-SHOW',
        'invoice_date' => '2026-01-01',
        'status' => DocumentStatus::PAID,
        'notes' => 'Counter sale',
    ]);
    SalesReturn::factory()->for($party, 'party')->create([
        'document_number' => 'SRT-SHOW',
        'return_date' => '2026-01-02',
        'status' => DocumentStatus::APPROVED,
    ]);
    Purchase::factory()->for($party, 'party')->create([
        'document_number' => 'PUR-SHOW',
        'invoice_date' => '2026-01-03',
        'status' => DocumentStatus::PENDING,
    ]);
    PurchaseReturn::factory()->for($party, 'party')->create([
        'document_number' => 'PRT-SHOW',
        'return_date' => '2026-01-04',
        'status' => DocumentStatus::PAID,
    ]);
    Payment::factory()->for($party, 'party')->create([
        'document_number' => 'PAY-SHOW',
        'type' => PaymentType::recipt,
        'payment_date' => '2026-01-05',
        'amount' => 45,
        'notes' => 'Paid at counter',
    ]);

    $component = Livewire::actingAs($user)->test('livewire.party.show', ['party' => $party]);

    expect($component->html())
        ->toContain('SALE-SHOW')
        ->toContain('SRT-SHOW')
        ->toContain('PUR-SHOW')
        ->toContain('PRT-SHOW')
        ->toContain('PAY-SHOW')
        ->toContain('Sales return')
        ->toContain('Purchase return')
        ->toContain('Payment received')
        ->toContain('Counter sale')
        ->toContain('Paid at counter')
        ->toContain(route('sales.invoice_preview', $sale));
});

test('party show page orders transactions newest first', function () {
    $user = User::factory()->create();
    $party = Party::factory()->create();

    Sale::factory()->for($party, 'party')->create(['document_number' => 'SALE-OLD', 'invoice_date' => '2026-01-01']);
    Sale::factory()->for($party, 'party')->create(['document_number' => 'SALE-LATEST', 'invoice_date' => '2026-01-03']);
    Sale::factory()->for($party, 'party')->create(['document_number' => 'SALE-MIDDLE', 'invoice_date' => '2026-01-02']);

    $html = Livewire::actingAs($user)
        ->test('livewire.party.show', ['party' => $party])
        ->html();

    expect($html)->toMatch('/SALE-LATEST.*SALE-MIDDLE.*SALE-OLD/s');
});

test('party show page paginates transactions ten at a time', function () {
    $user = User::factory()->create();
    $party = Party::factory()->create();

    foreach (range(1, 12) as $number) {
        Sale::factory()->for($party, 'party')->create([
            'document_number' => sprintf('SALE-%02d', $number),
            'invoice_date' => '2026-01-01',
        ]);
    }

    $component = Livewire::actingAs($user)->test('livewire.party.show', ['party' => $party]);

    expect($component->html())->toContain('SALE-12')->not->toContain('SALE-01');

    $component->call('gotoPage', 2);

    expect($component->html())->toContain('SALE-01')->not->toContain('SALE-12');
});

test('party show page shows an empty state without transactions', function () {
    $user = User::factory()->create();
    $party = Party::factory()->create();

    Livewire::actingAs($user)->test('livewire.party.show', ['party' => $party])
        ->assertSee(__('parties.no_transactions'));
});

test('party show page returns 404 for a missing party', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('parties.show', 999999))->assertNotFound();
});

test('party show page returns 404 for a soft deleted party', function () {
    $user = User::factory()->create();
    $party = Party::factory()->create();
    $party->delete();

    $this->actingAs($user)->get(route('parties.show', $party))->assertNotFound();
});

test('draft purchases do not inflate the supplier balance', function () {
    $user = User::factory()->create();
    $supplier = Party::factory()->supplier()->create();

    Purchase::factory()->for($supplier, 'party')->create([
        'total_amount' => 200,
        'status' => DocumentStatus::DRAFT,
    ]);
    Purchase::factory()->for($supplier, 'party')->create([
        'total_amount' => 50,
        'status' => DocumentStatus::PENDING,
    ]);

    $totals = Livewire::actingAs($user)
        ->test('livewire.party.show', ['party' => $supplier])
        ->instance()
        ->totals();

    expect($totals['purchases'])->toBe(50.0)
        ->and($totals['balance'])->toBe(-50.0);
});
