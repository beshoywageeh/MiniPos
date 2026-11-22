# Document Sequences Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a tested document-number generator with reset periods, a read/edit management page for the six document sequences, and wire the demo seeder to the service.

**Architecture:** `App\Support\DocumentNumber` formats and increments a `document_sequences` row inside a `lockForUpdate` transaction, resetting by day/week/month/year or never. One Livewire 4 index page lists the sequences (no create/delete) with one edit child component. `ResetPeriod` gains the missing `NEVER` case and its labels.

**Tech Stack:** Laravel 13, PHP 8.5, Livewire 4.4 SFC, Pest 5, Blade components (`x-card`, `x-table`, `x-modal`, `x-form.*`, `x-button`), SQLite (dev and tests).

**Spec:** `docs/superpowers/specs/2026-11-20-products-settings-document-sequences-design.md` (section C and Rollout)

## Global Constraints

- Routes use `Route::livewire()` inside the existing `['middleware' => ['auth']]` group in `routes/web.php`.
- Always pass `--no-interaction` to Artisan commands.
- Document types are application-defined strings: `sale`, `purchase`, `payment`, `expense`, `sales_return`, `purchase_return`. The UI never creates or deletes rows.
- Numbers are `prefix-` followed by `str_pad(next_number, padding, '0', STR_PAD_LEFT)`.
- Reset boundaries use the application timezone (`now()`).
- A missing sequence throws `RuntimeException("No document sequence configured for [type].")`; do not fall back to a default.
- Do not stage or commit the user's unrelated uncommitted work; stage only the files listed in a task.
- After changing PHP, run `vendor/bin/pint <paths> --format agent`.
- Narrow test command: `vendor/bin/pest tests/Feature/DocumentSequenceTest.php --compact`.

## Review Focus

- `DocumentSequence::last_reset_at` must be cast to `datetime`; without it the Carbon comparisons (`isSameDay`, `isSameWeek`, …) receive a string and break.
- The first call for a sequence whose `last_reset_at` is `null` resets `next_number` to 1 and stamps the timestamp. That is the spec's rule and preserves `DatabaseSeedingTest`'s `count + 1` assertion on a fresh seed.
- The index preview must be produced by the same formatter the generator uses (`DocumentNumber::preview()`), so the two can never drift.
- `Sale::$fillable` omits `document_number`, so `Sale::create()` silently drops it; the model is fixed in Task 5.
- Editing the prefix or lowering the next number may collide with existing documents; the form warns but does not block.
- `DemoDataSeeder` still must not be re-run on an already seeded database; the service does not deduplicate document numbers.

---

### Task 1: Reset period, sequence model and number generator

**Files:**
- Modify: `app/Enums/ResetPeriod.php`
- Create: `lang/en/ResetPeriod.php`
- Modify: `app/Models/DocumentSequence.php`
- Create: `app/Support/DocumentNumber.php`
- Test: `tests/Feature/DocumentSequenceTest.php`

**Interfaces:**
- Produces: `ResetPeriod::NEVER = 'never'` with a label; `DocumentSequence::factory()`; `DocumentNumber::next(string $documentType): string`; `DocumentNumber::preview(DocumentSequence $sequence): string`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/DocumentSequenceTest.php`:

```php
<?php

use App\Enums\ResetPeriod;
use App\Models\DocumentSequence;
use App\Support\DocumentNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a number uses the row prefix and padding and increments the sequence', function () {
    $sequence = DocumentSequence::factory()->forDocumentType('invoice', 'INV')->create([
        'next_number' => 7,
        'padding' => 4,
        'reset_period' => ResetPeriod::NEVER,
    ]);

    expect(DocumentNumber::next('invoice'))->toBe('INV-0007')
        ->and($sequence->refresh()->next_number)->toBe(8);
});

test('consecutive calls return consecutive numbers', function () {
    DocumentSequence::factory()->forDocumentType('sale', 'SALE')->create([
        'next_number' => 1,
        'padding' => 6,
        'reset_period' => ResetPeriod::NEVER,
    ]);

    expect(DocumentNumber::next('sale'))->toBe('SALE-000001')
        ->and(DocumentNumber::next('sale'))->toBe('SALE-000002')
        ->and(DocumentNumber::next('sale'))->toBe('SALE-000003');
});

test('a sequence resets when its period has rolled over', function (ResetPeriod $period, string $lastResetAt) {
    $this->travelTo('2026-11-20 10:00:00');

    $sequence = DocumentSequence::factory()->forDocumentType('sale', 'SALE')->create([
        'next_number' => 42,
        'padding' => 6,
        'reset_period' => $period,
        'last_reset_at' => $lastResetAt,
    ]);

    expect(DocumentNumber::next('sale'))->toBe('SALE-000001')
        ->and($sequence->refresh()->next_number)->toBe(2)
        ->and($sequence->last_reset_at->toDateString())->toBe('2026-11-20');
})->with([
    'daily' => [ResetPeriod::DAILY, '2026-11-19 10:00:00'],
    'weekly' => [ResetPeriod::WEEKLY, '2026-11-09 10:00:00'],
    'monthly' => [ResetPeriod::MONTHLY, '2026-10-20 10:00:00'],
    'yearly' => [ResetPeriod::YEARLY, '2025-11-20 10:00:00'],
]);

test('a sequence does not reset inside its period', function () {
    $this->travelTo('2026-11-20 10:00:00');

    DocumentSequence::factory()->forDocumentType('sale', 'SALE')->create([
        'next_number' => 7,
        'padding' => 6,
        'reset_period' => ResetPeriod::DAILY,
        'last_reset_at' => now()->startOfDay(),
    ]);

    expect(DocumentNumber::next('sale'))->toBe('SALE-000007');
});

test('a never sequence never resets', function () {
    $this->travelTo('2026-11-20 10:00:00');

    DocumentSequence::factory()->forDocumentType('sale', 'SALE')->create([
        'next_number' => 9,
        'padding' => 6,
        'reset_period' => ResetPeriod::NEVER,
        'last_reset_at' => now()->subYears(2),
    ]);

    expect(DocumentNumber::next('sale'))->toBe('SALE-000009');
});

test('an unknown document type throws', function () {
    expect(fn () => DocumentNumber::next('missing'))
        ->toThrow(RuntimeException::class, 'No document sequence configured for [missing].');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/DocumentSequenceTest.php --compact`

Expected: FAIL — `ResetPeriod::NEVER` is undefined and `App\Support\DocumentNumber` does not exist.

- [ ] **Step 3: Add the missing reset period and labels**

Replace `app/Enums/ResetPeriod.php` with:

```php
<?php

namespace App\Enums;

enum ResetPeriod: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';
    case NEVER = 'never';

    public function label(): string
    {
        return match ($this) {
            self::DAILY => __('ResetPeriod.Daily'),
            self::WEEKLY => __('ResetPeriod.Weekly'),
            self::MONTHLY => __('ResetPeriod.Monthly'),
            self::YEARLY => __('ResetPeriod.Yearly'),
            self::NEVER => __('ResetPeriod.Never'),
        };
    }
}
```

Create `lang/en/ResetPeriod.php`:

```php
<?php

return [
    'Daily' => 'Daily',
    'Weekly' => 'Weekly',
    'Monthly' => 'Monthly',
    'Yearly' => 'Yearly',
    'Never' => 'Never',
];
```

- [ ] **Step 4: Wire the model**

In `app/Models/DocumentSequence.php`, import `Illuminate\Database\Eloquent\Factories\HasFactory`, add `use HasFactory;`, and add the datetime cast:

```php
    protected $casts = [
        'reset_period' => ResetPeriod::class,
        'last_reset_at' => 'datetime',
    ];
```

Leave `$fillable` unchanged.

- [ ] **Step 5: Create the generator**

Create `app/Support/DocumentNumber.php`:

```php
<?php

namespace App\Support;

use App\Enums\ResetPeriod;
use App\Models\DocumentSequence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DocumentNumber
{
    public static function next(string $documentType): string
    {
        return DB::transaction(function () use ($documentType): string {
            $sequence = DocumentSequence::query()
                ->where('document_type', $documentType)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                throw new RuntimeException("No document sequence configured for [{$documentType}].");
            }

            $now = now();

            if (self::shouldReset($sequence->reset_period, $sequence->last_reset_at, $now)) {
                $sequence->next_number = 1;
                $sequence->last_reset_at = $now;
            } elseif ($sequence->last_reset_at === null) {
                $sequence->last_reset_at = $now;
            }

            $number = self::preview($sequence);

            $sequence->next_number++;
            $sequence->save();

            return $number;
        });
    }

    public static function preview(DocumentSequence $sequence): string
    {
        return sprintf(
            '%s-%s',
            $sequence->prefix,
            str_pad((string) $sequence->next_number, $sequence->padding, '0', STR_PAD_LEFT),
        );
    }

    private static function shouldReset(ResetPeriod $period, ?Carbon $lastResetAt, Carbon $now): bool
    {
        if ($period === ResetPeriod::NEVER) {
            return false;
        }

        if ($lastResetAt === null) {
            return true;
        }

        return match ($period) {
            ResetPeriod::DAILY => ! $lastResetAt->isSameDay($now),
            ResetPeriod::WEEKLY => ! $lastResetAt->isSameWeek($now),
            ResetPeriod::MONTHLY => ! $lastResetAt->isSameMonth($now),
            ResetPeriod::YEARLY => ! $lastResetAt->isSameYear($now),
            ResetPeriod::NEVER => false,
        };
    }
}
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/DocumentSequenceTest.php --compact`

Expected: PASS (9 tests: six declarations, the rollover dataset contributes four cases).

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint app/Enums/ResetPeriod.php app/Models/DocumentSequence.php app/Support/DocumentNumber.php lang/en/ResetPeriod.php tests/Feature/DocumentSequenceTest.php --format agent
git add app/Enums/ResetPeriod.php app/Models/DocumentSequence.php app/Support/DocumentNumber.php lang/en/ResetPeriod.php tests/Feature/DocumentSequenceTest.php
git commit -m "feat(document-sequences): reset periods and number generator" --no-verify
```

---

### Task 2: Route, language files and sidebar link

**Files:**
- Modify: `routes/web.php`
- Modify: `resources/views/components/layout/sidebar.blade.php`
- Modify: `lang/en/dashboard.php`
- Create: `lang/en/document_sequences.php`
- Test: `tests/Feature/DocumentSequenceTest.php`

**Interfaces:**
- Produces: named route `document-sequences.index` → `/document-sequences`; component name `livewire.document-sequences.index`; sidebar item; `dashboard.nav_document_sequences`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/DocumentSequenceTest.php` (add `use App\Models\User;` to the imports):

```php
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
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/DocumentSequenceTest.php --compact`

Expected: FAIL — route `document-sequences.index` is not defined.

- [ ] **Step 3: Register the route**

In `routes/web.php`, after the settings route, add:

```php
Route::livewire('/document-sequences', 'livewire.document-sequences.index')->name('document-sequences.index');
```

- [ ] **Step 4: Add the language files**

Create `lang/en/document_sequences.php`:

```php
<?php

return [
    'title' => 'Document Sequences',
    'subtitle' => 'Control the numbering and reset period for each document type.',
    'type' => 'Document type',
    'prefix' => 'Prefix',
    'next_number' => 'Next number',
    'preview' => 'Preview',
    'padding' => 'Padding',
    'reset_period' => 'Reset period',
    'last_reset' => 'Last reset',
    'edit' => 'Edit sequence',
    'updated' => 'Document sequence updated successfully.',
    'duplicate_warning' => 'Lowering the next number can produce duplicate document numbers.',
];
```

Add `'nav_document_sequences' => 'Document Sequences',` to `lang/en/dashboard.php` after `nav_settings`.

- [ ] **Step 5: Add the sidebar item**

In `resources/views/components/layout/sidebar.blade.php`, add this entry after the Settings entry:

```php
['label' => __('dashboard.nav_document_sequences'), 'href' => route('document-sequences.index'), 'active' => request()->routeIs('document-sequences.*'), 'navigate' => true, 'path' => 'M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5'],
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/DocumentSequenceTest.php --compact`

Expected: PASS (11 tests).

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint tests/Feature/DocumentSequenceTest.php --format agent
git add routes/web.php resources/views/components/layout/sidebar.blade.php lang/en/dashboard.php lang/en/document_sequences.php tests/Feature/DocumentSequenceTest.php
git commit -m "feat(document-sequences): route, language files and sidebar link" --no-verify
```

---

### Task 3: Document sequences index page

**Files:**
- Create: `resources/views/components/livewire/document-sequences/⚡index.blade.php`
- Test: `tests/Feature/DocumentSequenceTest.php`

**Interfaces:**
- Consumes: `DocumentSequence`, `DocumentNumber::preview()` from Task 1; `document-sequences.index` from Task 2.
- Produces: component `livewire.document-sequences.index` with computed `sequences()`; `refreshList()` listening on `document-sequence-updated`.

- [ ] **Step 1: Write the failing test**

Append (add `use Livewire\Livewire;` to the imports):

```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Feature/DocumentSequenceTest.php --compact`

Expected: FAIL — component `livewire.document-sequences.index` not found.

- [ ] **Step 3: Implement the index SFC**

Create `resources/views/components/livewire/document-sequences/⚡index.blade.php`.

PHP block requirements:

```php
use App\Models\DocumentSequence;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function sequences()
    {
        return DocumentSequence::query()->orderBy('document_type')->get();
    }

    #[On('document-sequence-updated')]
    public function refreshList(): void
    {
        //
    }
};
```

Blade requirements:

- Root `<div class="mx-auto max-w-7xl space-y-6">` with an Alpine flash block for `document-sequence-updated`, using `document_sequences.updated`, matching the categories index.
- `<x-page-header :title="__('document_sequences.title')" :subtitle="__('document_sequences.subtitle')" />` with no action button (no create).
- `<x-card>` with `<x-table>`; columns: `document_sequences.type`, `prefix`, `next_number`, `preview`, `padding`, `reset_period`, `last_reset`, actions.
- Row loop `@forelse ($this->sequences as $sequence)` with `wire:key="document-sequence-{{ $sequence->id }}"`.
- Type cell: `{{ str($sequence->document_type)->headline() }}`.
- Preview cell: `{{ DocumentNumber::preview($sequence) }}` (use the fully qualified `\App\Support\DocumentNumber::preview($sequence)` inside Blade, since PHP-block imports do not reach the template).
- Reset period cell: `{{ $sequence->reset_period->label() }}`.
- Last reset cell: `{{ $sequence->last_reset_at?->format('Y-m-d H:i') ?? '—' }}`.
- Actions cell: `{{-- edit component wired in Task 4 --}}`.
- Empty state `@empty` row with `colspan="8"` showing `—` (all six rows come from the seeder; the empty state is only a guard).
- No pagination; there are six rows.

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Feature/DocumentSequenceTest.php --compact`

Expected: PASS (12 tests).

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint tests/Feature/DocumentSequenceTest.php --format agent
git add "resources/views/components/livewire/document-sequences/⚡index.blade.php" tests/Feature/DocumentSequenceTest.php
git commit -m "feat(document-sequences): index page with previews" --no-verify
```

---

### Task 4: Edit component

**Files:**
- Create: `resources/views/components/livewire/document-sequences/actions/⚡edit.blade.php`
- Modify: `resources/views/components/livewire/document-sequences/⚡index.blade.php`
- Test: `tests/Feature/DocumentSequenceTest.php`

**Interfaces:**
- Consumes: `DocumentSequence`, `ResetPeriod`.
- Produces: component `livewire.document-sequences.actions.edit` with public `$sequence`, `$prefix`, `$nextNumber`, `$padding`, `$resetPeriod`; method `update(): void`; event `document-sequence-updated`.

- [ ] **Step 1: Write the failing tests**

Append:

```php
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
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/DocumentSequenceTest.php --compact`

Expected: FAIL — component `livewire.document-sequences.actions.edit` not found.

- [ ] **Step 3: Implement the edit SFC**

Create `resources/views/components/livewire/document-sequences/actions/⚡edit.blade.php`.

PHP requirements:

```php
use App\Enums\ResetPeriod;
use App\Models\DocumentSequence;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public DocumentSequence $sequence;

    public string $prefix = '';

    public string $nextNumber = '';

    public string $padding = '';

    public string $resetPeriod = '';

    public function mount(DocumentSequence $sequence): void
    {
        $this->sequence = $sequence;
        $this->prefix = $sequence->prefix;
        $this->nextNumber = (string) $sequence->next_number;
        $this->padding = (string) $sequence->padding;
        $this->resetPeriod = $sequence->reset_period->value;
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function resetPeriodOptions(): array
    {
        return collect(ResetPeriod::cases())
            ->mapWithKeys(fn (ResetPeriod $period): array => [$period->value => $period->label()])
            ->all();
    }

    public function update(): void
    {
        $this->validate([
            'prefix' => ['required', 'string', 'max:20'],
            'nextNumber' => ['required', 'integer', 'min:1'],
            'padding' => ['required', 'integer', 'min:1', 'max:10'],
            'resetPeriod' => ['required', Rule::in(array_column(ResetPeriod::cases(), 'value'))],
        ]);

        $this->sequence->update([
            'prefix' => $this->prefix,
            'next_number' => $this->nextNumber,
            'padding' => $this->padding,
            'reset_period' => $this->resetPeriod,
        ]);

        $this->dispatch('document-sequence-updated');
    }
};
```

Blade requirements:

- `<x-modal maxWidth="md" @document-sequence-updated.window="open = false">`.
- Trigger: ghost button with the pencil icon and `general.edit`; title `document_sequences.edit`.
- Form `wire:submit="update"`: prefix (input), next number (number, min 1), padding (number, min 1, max 10), reset period (`x-form.select` with `$this->resetPeriodOptions`, `:selected="$resetPeriod"`).
- Under the next-number field, a help line with `document_sequences.duplicate_warning` (pass `:help="__('document_sequences.duplicate_warning')"` to `x-form.input`).
- Cancel and Save buttons at the end of the form body.

Wire the component into the index actions cell:

```blade
<livewire:livewire.document-sequences.actions.edit :sequence="$sequence"
    wire:key="document-sequence-edit-{{ $sequence->id }}" />
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/DocumentSequenceTest.php --compact`

Expected: PASS (14 tests).

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint tests/Feature/DocumentSequenceTest.php --format agent
git add "resources/views/components/livewire/document-sequences/actions/⚡edit.blade.php" "resources/views/components/livewire/document-sequences/⚡index.blade.php" tests/Feature/DocumentSequenceTest.php
git commit -m "feat(document-sequences): edit component with duplicate warning" --no-verify
```

---

### Task 5: Seeder integration, sale fillable, whole-feature verification

**Files:**
- Modify: `database/seeders/DemoDataSeeder.php`
- Modify: `app/Models/Sale.php`
- Test: `tests/Feature/DocumentSequenceTest.php`

**Interfaces:**
- Consumes: `DocumentNumber::next()` from Task 1.
- Produces: `Sale::$fillable` includes `document_number`; the demo seeder no longer has its own formatter.

- [ ] **Step 1: Write the failing test**

Append (add `use App\Enums\DocumentStatus;`, `use App\Models\Party;`, `use App\Models\Sale;`, `use App\Models\Warehouse;` to the imports):

```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Feature/DocumentSequenceTest.php --compact`

Expected: FAIL — `document_number` is not fillable, so the insert violates its NOT NULL constraint.

- [ ] **Step 3: Make the document number fillable**

In `app/Models/Sale.php`, add `'document_number',` as the first entry of `$fillable`.

- [ ] **Step 4: Point the demo seeder at the service**

In `database/seeders/DemoDataSeeder.php`:

- Add `use App\Support\DocumentNumber;`.
- Remove `use App\Models\DocumentSequence;`.
- Replace the seven `$this->nextDocumentNumber('...')` call sites with `DocumentNumber::next('...')`.
- Delete the private `nextDocumentNumber()` method entirely.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/DocumentSequenceTest.php --compact`

Expected: PASS (15 tests).

Run the seeding suite:

Run: `vendor/bin/pest tests/Feature/DatabaseSeedingTest.php --compact`

Expected: PASS, including `advances every document sequence past the seeded documents`.

- [ ] **Step 6: Run the full suite and format**

Run: `vendor/bin/pest --compact`

Expected: PASS. If `DatabaseSeedingTest` fails on the known brand-code flake, rerun that file alone to confirm before reporting.

Run: `vendor/bin/pint app/Models/Sale.php database/seeders/DemoDataSeeder.php app/Support/DocumentNumber.php app/Models/DocumentSequence.php app/Enums/ResetPeriod.php tests/Feature/DocumentSequenceTest.php --format agent`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/seeders/DemoDataSeeder.php app/Models/Sale.php tests/Feature/DocumentSequenceTest.php
git commit -m "feat(document-sequences): seed through the generator and allow sale document numbers" --no-verify
```

---

### Task 6: Whole-feature verification after formatting

**Files:**
- Verify only; no new files expected.

- [ ] **Step 1: Re-run the focused suite**

Run: `vendor/bin/pest tests/Feature/DocumentSequenceTest.php --compact`

Expected: PASS (15 tests).

- [ ] **Step 2: Re-run the full suite**

Run: `vendor/bin/pest --compact`

Expected: PASS.

- [ ] **Step 3: Commit any formatting-only changes**

```bash
git add app/Models/Sale.php database/seeders/DemoDataSeeder.php tests/Feature/DocumentSequenceTest.php
git commit -m "chore(document-sequences): formatting" --no-verify
```

Only commit if `git status` shows changes; skip otherwise.
