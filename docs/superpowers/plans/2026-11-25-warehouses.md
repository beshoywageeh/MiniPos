# Warehouse Management & Sales Link Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Warehouse area for creating, editing, searching, and deactivating warehouses, plus a warehouse detail page listing that warehouse's sales with invoice-preview links.

**Architecture:** Two authenticated full-page Livewire 4 SFCs use the existing `Warehouse` model and `Warehouse::sales()` relation; no controller, model, migration, or sale-form changes are needed. The index uses computed queries with pagination and modal child components; the show page uses computed aggregates and a paginated sales table. Deactivation only toggles `is_active`, so the New Sale dropdown's existing active filter hides retired warehouses while historical sales remain visible.

**Tech Stack:** Laravel 13.40.0, PHP 8.5, Livewire 4.4.8 SFCs, Pest 5.3.5, Blade components (`x-card`, `x-table`, `x-modal`, `x-form.*`, `x-button`, `x-badge`, `x-page-header`), SQLite.

**Spec:** `docs/superpowers/specs/2026-11-25-warehouse-management-sales-link-design.md`

## Global Constraints

- Routes use `Route::livewire()` inside the existing `['middleware' => ['auth']]` group in `routes/web.php`.
- Livewire SFC filenames keep the `⚡` prefix and live under `resources/views/components/livewire/`.
- Auth-only access: no permission system or authorization changes.
- The existing empty `WarehouseController`, `Warehouse` model, migrations, seeders, and sale form stay unchanged.
- Never delete or soft-delete warehouses; retirement is only `is_active => false`.
- Validation: `code` required/string/max 20/unique `warehouses.code` (ignore own id on update); `name` required/string/max 255; `address` nullable/string; `note` nullable/string; `isActive` boolean.
- Search matches `code` and `name`; all lists paginate 10 records.
- Invoice links use the unchanged `sales.invoice_preview` named route.
- Prefer named routes and `route()` for all links.
- English copy lives in `lang/en/warehouses.php` and `lang/en/dashboard.php`.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes and `vendor/bin/phpstan analyse --no-progress` before completion.
- Narrow test command: `vendor/bin/pest tests/Feature/WarehousePagesTest.php --compact`.
- Full verification: `vendor/bin/pest --compact`.

## Review Focus

- A code longer than 20 characters can pass if only required/unique are tested; Task 2 pins the max rule.
- HTML in a warehouse note is rendered on the show page and must be escaped; Task 5 pins escaping there.
- Null `address`/`note` values must render safely without errors; Task 5 pins a warehouse with null fields.
- A sale without a customer must render the walk-in label, not a blank customer cell; Task 5 pins `party_id = null`.
- A deactivated warehouse must still show historical sales; Task 5 pins inactive show-page access.

---

## File Structure

- Create `resources/views/components/livewire/warehouses/⚡index.blade.php`: searchable, paginated warehouse table, active badge, sales count, view-sales link, refresh listeners, and flash banner.
- Create `resources/views/components/livewire/warehouses/actions/⚡create.blade.php`: create modal with validation and `warehouse-created` event.
- Create `resources/views/components/livewire/warehouses/actions/⚡update.blade.php`: update modal with validation and `warehouse-updated` event.
- Create `resources/views/components/livewire/warehouses/⚡show.blade.php`: warehouse details, computed sales summary, and paginated sales table.
- Create `lang/en/warehouses.php`: all warehouse copy.
- Create `tests/Feature/WarehousePagesTest.php`: all warehouse feature tests.
- Modify `routes/web.php`: add `warehouses.index` and `warehouses.show`.
- Modify `resources/views/components/layout/sidebar.blade.php`: add Warehouse nav item after Products.
- Modify `lang/en/dashboard.php`: add `nav_warehouses`.

---

### Task 1: Warehouse index route, language, and page

**Files:**
- Modify: `routes/web.php`
- Create: `lang/en/warehouses.php`
- Create: `resources/views/components/livewire/warehouses/⚡index.blade.php`
- Test: `tests/Feature/WarehousePagesTest.php`

**Interfaces:**
- Consumes: `Warehouse::sales()` relation, `Sale::factory()`, `Warehouse::factory()`, `User::factory()`, shared Blade components.
- Produces: full-page component `livewire.warehouses.index` with `#[Url] public string $search = ''`, `#[Computed] public function warehouses()` returning a 10-per-page paginator with `sales_count`, `refreshList(): void`, and named route `warehouses.index`.
- Produces these exact lang keys in `lang/en/warehouses.php`: `title`, `subtitle`, `add`, `edit`, `deactivate`, `activate`, `view_sales`, `code`, `name`, `address`, `note`, `active`, `inactive`, `sales`, `sales_count`, `sales_total`, `created`, `updated`, `activated`, `deactivated`, `no_records`, `no_sales`, `details`, `back`, `document_number`, `invoice_date`, `customer`, `total`, `status`, `invoice`.

- [ ] **Step 1: Write the failing tests**

Add `tests/Feature/WarehousePagesTest.php` with these imports/setup and tests:

```php
<?php

use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('warehouses index route exists and requires auth', function () {
    expect(route('warehouses.index'))->toBe(url('/warehouses'));

    $this->get(route('warehouses.index'))->assertRedirect();
});

test('warehouses index lists warehouses with code, name, address, status, and sales count', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create([
        'code' => 'WH-100',
        'name' => 'Main Store',
        'address' => '12 Main Street',
    ]);
    Sale::factory()->for($warehouse)->create();

    $component = Livewire::actingAs($user)->test('livewire.warehouses.index')
        ->assertSee('WH-100')
        ->assertSee('Main Store')
        ->assertSee('12 Main Street')
        ->assertSee(__('warehouses.active'));

    expect($component->instance()->warehouses()->first()->sales_count)->toBe(1);
});

test('warehouses search matches the given field', function (string $value) {
    $user = User::factory()->create();
    Warehouse::factory()->create(['name' => 'Annex', 'code' => 'WH-111']);
    Warehouse::factory()->create(['name' => 'Depot', 'code' => 'WH-222']);

    Livewire::actingAs($user)->test('livewire.warehouses.index')
        ->set('search', $value)
        ->assertSee($value)
        ->assertDontSee($value === 'Annex' ? 'Depot' : 'Annex');
})->with([
    'name' => ['Annex'],
    'code' => ['WH-222'],
]);

test('warehouses search with no match shows the empty state', function () {
    $user = User::factory()->create();
    Warehouse::factory()->create(['name' => 'Annex']);

    Livewire::actingAs($user)->test('livewire.warehouses.index')
        ->set('search', 'zzzz')
        ->assertSee(__('warehouses.no_records'));
});

test('warehouses index paginates at ten records', function () {
    $user = User::factory()->create();

    foreach (range(1, 11) as $number) {
        Warehouse::factory()->create(['name' => sprintf('Warehouse %02d', $number)]);
    }

    Livewire::actingAs($user)->test('livewire.warehouses.index')
        ->assertSee('Warehouse 11')
        ->assertDontSee('Warehouse 01');
});

test('warehouses index refreshes when warehouse events fire', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create(['name' => 'Before Update']);

    $component = Livewire::actingAs($user)->test('livewire.warehouses.index');

    expect($component->html())->toContain('Before Update');

    $warehouse->update(['name' => 'After Update']);

    $component->dispatch('warehouse-created');
    $component->dispatch('warehouse-updated');

    expect($component->html())->toContain('After Update');
});

test('warehouses index escapes user supplied content', function () {
    $user = User::factory()->create();
    $name = '<script>alert("xss")</script>';
    $address = '<img src=x onerror=alert(1)>';
    Warehouse::factory()->create(['name' => $name, 'address' => $address]);

    Livewire::actingAs($user)->test('livewire.warehouses.index')
        ->assertSee(e($name), false)
        ->assertSee(e($address), false)
        ->assertDontSee($name, false)
        ->assertDontSee($address, false);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/WarehousePagesTest.php --compact`

Expected: FAIL because `warehouses.index` route and component do not exist.

- [ ] **Step 3: Implement the index**

Add `Route::livewire('/warehouses', 'livewire.warehouses.index')->name('warehouses.index');` inside the auth group in `routes/web.php`.

Create `lang/en/warehouses.php` with the exact keys listed in Interfaces. Use the copy from the spec's field/status/flash labels; `no_records` is `No warehouses found.` and `no_sales` is `No sales found for this warehouse.`.

Create `resources/views/components/livewire/warehouses/⚡index.blade.php` following `catalog/brands/⚡index.blade.php`: `WithPagination`, `#[Url] $search`, `#[Computed] warehouses()` with `withCount('sales')`, code/name `like` search, `latest('id')->paginate(10)`, `#[On]` listeners for `warehouse-created` and `warehouse-updated`, flash banner, `x-page-header`, `x-card`, search input with `wire:model.live.debounce.300ms="search"`, table columns `#`, code, name, address, active badge, sales count, created date, actions, `wire:key="warehouse-{{ $warehouse->id }}"`, empty state, and links to `route('warehouses.show', $warehouse)`. The actions cell may be empty for now; later tasks fill it.

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/WarehousePagesTest.php --compact`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add routes/web.php lang/en/warehouses.php resources/views/components/livewire/warehouses/⚡index.blade.php tests/Feature/WarehousePagesTest.php
git commit -m "feat(warehouses): add warehouse index page"
```

### Task 2: Warehouse create modal

**Files:**
- Create: `resources/views/components/livewire/warehouses/actions/⚡create.blade.php`
- Modify: `resources/views/components/livewire/warehouses/⚡index.blade.php`
- Test: `tests/Feature/WarehousePagesTest.php`

**Interfaces:**
- Consumes: `Warehouse` model, `Rule::unique('warehouses', 'code')`, shared `x-modal`, `x-form.*`, `x-button` components.
- Produces: component `livewire.warehouses.actions.create` with public properties `code`, `name`, `address`, `note`, `isActive`, public `create(): void`, and event `warehouse-created`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/WarehousePagesTest.php`:

```php
test('a warehouse can be created', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.warehouses.actions.create')
        ->set('code', 'WH-NEW')
        ->set('name', 'Annex')
        ->set('address', '9 Annex Road')
        ->set('note', 'Back room')
        ->set('isActive', false)
        ->call('create')
        ->assertDispatched('warehouse-created')
        ->assertHasNoErrors();

    $warehouse = Warehouse::where('code', 'WH-NEW')->firstOrFail();

    expect($warehouse->name)->toBe('Annex')
        ->and($warehouse->address)->toBe('9 Annex Road')
        ->and($warehouse->note)->toBe('Back room')
        ->and($warehouse->is_active)->toBeFalse();
});

test('warehouse creation rejects a duplicate code', function () {
    Warehouse::factory()->create(['code' => 'WH-TAKEN']);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.warehouses.actions.create')
        ->set('code', 'WH-TAKEN')
        ->set('name', 'Other')
        ->call('create')
        ->assertHasErrors(['code' => 'unique'])
        ->assertNotDispatched('warehouse-created');
});

test('warehouse creation rejects missing required fields', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.warehouses.actions.create')
        ->set('code', '')
        ->set('name', '')
        ->call('create')
        ->assertHasErrors(['code' => 'required', 'name' => 'required'])
        ->assertNotDispatched('warehouse-created');
});

test('warehouse creation rejects a code longer than twenty characters', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.warehouses.actions.create')
        ->set('code', str_repeat('X', 21))
        ->set('name', 'Long Code')
        ->call('create')
        ->assertHasErrors(['code' => 'max'])
        ->assertNotDispatched('warehouse-created');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/WarehousePagesTest.php --compact --filter=warehouse`

Expected: FAIL because the create component does not exist.

- [ ] **Step 3: Implement the create modal**

Create `resources/views/components/livewire/warehouses/actions/⚡create.blade.php` following `catalog/brands/actions/⚡create.blade.php`. Use exactly the Global Constraints validation rules, `Warehouse::create([...])`, reset all five public properties, dispatch `warehouse-created`, and put Cancel/Save buttons at the end of the `<form>` body, never in the modal footer slot.

Add `<livewire:livewire.warehouses.actions.create />` to the index page header actions.

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/WarehousePagesTest.php --compact`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/livewire/warehouses/actions/⚡create.blade.php resources/views/components/livewire/warehouses/⚡index.blade.php tests/Feature/WarehousePagesTest.php
git commit -m "feat(warehouses): add create warehouse modal"
```

### Task 3: Warehouse update modal

**Files:**
- Create: `resources/views/components/livewire/warehouses/actions/⚡update.blade.php`
- Modify: `resources/views/components/livewire/warehouses/⚡index.blade.php`
- Test: `tests/Feature/WarehousePagesTest.php`

**Interfaces:**
- Consumes: `Warehouse $warehouse`, `Rule::unique('warehouses', 'code')->ignore($this->warehouse->id)`.
- Produces: component `livewire.warehouses.actions.update` with public `Warehouse $warehouse`, public properties `code`, `name`, `address`, `note`, `isActive`, `mount(Warehouse $warehouse): void`, public `update(): void`, and event `warehouse-updated`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/WarehousePagesTest.php`:

```php
test('a warehouse can be updated', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create([
        'code' => 'WH-KEEP',
        'name' => 'Old Main',
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test('livewire.warehouses.actions.update', ['warehouse' => $warehouse])
        ->assertSet('code', 'WH-KEEP')
        ->set('name', 'New Main')
        ->set('address', '77 New Road')
        ->set('note', 'Renovated')
        ->set('isActive', false)
        ->call('update')
        ->assertDispatched('warehouse-updated')
        ->assertHasNoErrors();

    expect($warehouse->refresh()->name)->toBe('New Main')
        ->and($warehouse->address)->toBe('77 New Road')
        ->and($warehouse->note)->toBe('Renovated')
        ->and($warehouse->is_active)->toBeFalse();
});

test('keeping its own code passes unique validation on warehouse update', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create(['code' => 'WH-KEEP']);

    Livewire::actingAs($user)
        ->test('livewire.warehouses.actions.update', ['warehouse' => $warehouse])
        ->set('name', 'Renamed')
        ->call('update')
        ->assertDispatched('warehouse-updated')
        ->assertHasNoErrors();

    expect($warehouse->refresh()->code)->toBe('WH-KEEP');
});

test('warehouse update rejects another warehouses code', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create(['code' => 'WH-KEEP']);
    Warehouse::factory()->create(['code' => 'WH-OTHER']);

    Livewire::actingAs($user)
        ->test('livewire.warehouses.actions.update', ['warehouse' => $warehouse])
        ->set('code', 'WH-OTHER')
        ->call('update')
        ->assertHasErrors(['code' => 'unique'])
        ->assertNotDispatched('warehouse-updated');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/WarehousePagesTest.php --compact --filter=warehouse`

Expected: FAIL because the update component does not exist.

- [ ] **Step 3: Implement the update modal**

Create `resources/views/components/livewire/warehouses/actions/⚡update.blade.php` following `catalog/brands/actions/⚡update.blade.php`. Prefill all five properties in `mount()`, use the Global Constraints validation rules with `ignore($this->warehouse->id)`, call `$this->warehouse->update([...])`, dispatch `warehouse-updated`, and put buttons at the end of the form body.

Add `<livewire:livewire.warehouses.actions.update :warehouse="$warehouse" wire:key="warehouse-update-{{ $warehouse->id }}" />` to each index row actions cell.

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/WarehousePagesTest.php --compact`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/livewire/warehouses/actions/⚡update.blade.php resources/views/components/livewire/warehouses/⚡index.blade.php tests/Feature/WarehousePagesTest.php
git commit -m "feat(warehouses): add update warehouse modal"
```

### Task 4: Activate/deactivate toggle and sales dropdown effect

**Files:**
- Modify: `resources/views/components/livewire/warehouses/⚡index.blade.php`
- Test: `tests/Feature/WarehousePagesTest.php`

**Interfaces:**
- Consumes: existing `livewire.sales.create` computed method `warehouseOptions(): array`, which filters `is_active = true`.
- Produces: `toggleActive(int $warehouseId): void` on `livewire.warehouses.index`; it finds the warehouse, flips only `is_active`, and dispatches `warehouse-updated`. No delete component and no confirmation modal.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/WarehousePagesTest.php`:

```php
test('deactivating a warehouse hides it from new sale warehouse options', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create(['name' => 'Old Store']);

    Livewire::actingAs($user)
        ->test('livewire.warehouses.index')
        ->call('toggleActive', $warehouse->id)
        ->assertDispatched('warehouse-updated');

    expect($warehouse->refresh()->is_active)->toBeFalse();

    $saleForm = Livewire::actingAs($user)->test('livewire.sales.create');

    expect(array_keys($saleForm->instance()->warehouseOptions()))->not->toContain($warehouse->id);
});

test('activating a warehouse restores it in new sale warehouse options', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create([
        'name' => 'Returning Store',
        'is_active' => false,
    ]);

    Livewire::actingAs($user)
        ->test('livewire.warehouses.index')
        ->call('toggleActive', $warehouse->id)
        ->assertDispatched('warehouse-updated');

    expect($warehouse->refresh()->is_active)->toBeTrue();

    $saleForm = Livewire::actingAs($user)->test('livewire.sales.create');

    expect(array_keys($saleForm->instance()->warehouseOptions()))->toContain($warehouse->id);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/WarehousePagesTest.php --compact --filter=activating`

Expected: FAIL because `toggleActive` does not exist.

- [ ] **Step 3: Implement the toggle**

Add `toggleActive(int $warehouseId): void` to the index component: resolve `Warehouse::findOrFail($warehouseId)`, set `is_active` to `! $warehouse->is_active`, save, and dispatch `warehouse-updated`.

Add a row action button labeled `warehouses.deactivate` when active and `warehouses.activate` when inactive, using `wire:click="toggleActive({{ $warehouse->id }})"`. Do not add a delete action.

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/WarehousePagesTest.php --compact`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/livewire/warehouses/⚡index.blade.php tests/Feature/WarehousePagesTest.php
git commit -m "feat(warehouses): toggle active status"
```

### Task 5: Warehouse show page with sales

**Files:**
- Modify: `routes/web.php`
- Create: `resources/views/components/livewire/warehouses/⚡show.blade.php`
- Test: `tests/Feature/WarehousePagesTest.php`

**Interfaces:**
- Consumes: `Warehouse::sales()`, `Sale::party`, `DocumentStatus::label()`, `sales.invoice_preview` route, `sales.no_customer` lang key.
- Produces: full-page component `livewire.warehouses.show` with public `Warehouse $warehouse`, `mount(Warehouse $warehouse): void`, `#[Computed] salesCount(): int`, `#[Computed] salesTotal(): float`, and `#[Computed] sales()` returning a 10-per-page paginator ordered by `latest('invoice_date')->latest('id')` with `with('party')`; named route `warehouses.show`.

- [ ] **Step 1: Write the failing tests**

Add imports for `App\Enums\DocumentStatus` and `App\Models\Party` to the test file. Append these tests:

```php
test('warehouses show route exists, requires auth, and returns 404 for a missing id', function () {
    $warehouse = Warehouse::factory()->create();
    $user = User::factory()->create();

    expect(route('warehouses.show', $warehouse))->toBe(url('/warehouses/'.$warehouse->id));

    $this->get(route('warehouses.show', $warehouse))->assertRedirect();
    $this->actingAs($user)->get(route('warehouses.show', 999999))->assertNotFound();
});

test('warehouse show page renders details and sales summary', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create([
        'code' => 'WH-SHOW',
        'name' => 'Depot West',
        'address' => '45 Depot Road',
        'note' => 'Loading bay',
    ]);
    Sale::factory()->for($warehouse)->create(['total_amount' => 123.45]);

    $component = Livewire::actingAs($user)
        ->test('livewire.warehouses.show', ['warehouse' => $warehouse])
        ->assertSee('WH-SHOW')
        ->assertSee('Depot West')
        ->assertSee('45 Depot Road')
        ->assertSee('Loading bay')
        ->assertSee(__('warehouses.active'))
        ->assertSee('123.45');

    expect($component->instance()->salesCount())->toBe(1)
        ->and($component->instance()->salesTotal())->toBe(123.45);
});

test('warehouse show page lists only its sales with customer status total and invoice link', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $customer = Party::factory()->customer()->create(['name' => 'Acme Cafe']);
    $sale = Sale::factory()->for($warehouse)->for($customer)->create([
        'document_number' => 'INV-100',
        'total_amount' => 75.50,
        'status' => DocumentStatus::PARTIAL,
    ]);

    Livewire::actingAs($user)
        ->test('livewire.warehouses.show', ['warehouse' => $warehouse])
        ->assertSee('INV-100')
        ->assertSee('Acme Cafe')
        ->assertSee('75.50')
        ->assertSee(DocumentStatus::PARTIAL->label())
        ->assertSee(route('sales.invoice_preview', $sale));
});

test('warehouse show page labels a sale without a customer', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    Sale::factory()->for($warehouse)->create(['party_id' => null]);

    Livewire::actingAs($user)
        ->test('livewire.warehouses.show', ['warehouse' => $warehouse])
        ->assertSee(__('sales.no_customer'));
});

test('warehouse show page paginates sales and excludes other warehouses', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $otherWarehouse = Warehouse::factory()->create();
    Sale::factory()->count(11)->for($warehouse)->create();
    Sale::factory()->for($otherWarehouse)->create(['document_number' => 'INV-OTHER']);

    $component = Livewire::actingAs($user)
        ->test('livewire.warehouses.show', ['warehouse' => $warehouse])
        ->assertDontSee('INV-OTHER');

    expect($component->instance()->sales()->total())->toBe(11)
        ->and($component->instance()->sales()->perPage())->toBe(10);
});

test('warehouse show page keeps historical sales for an inactive warehouse', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create(['is_active' => false]);
    Sale::factory()->for($warehouse)->create(['document_number' => 'INV-OLD']);

    Livewire::actingAs($user)
        ->test('livewire.warehouses.show', ['warehouse' => $warehouse])
        ->assertSee(__('warehouses.inactive'))
        ->assertSee('INV-OLD');
});

test('warehouse show page escapes user supplied content and renders null fields', function () {
    $user = User::factory()->create();
    $name = '<script>alert("xss")</script>';
    $note = '<img src=x onerror=alert(1)>';
    $warehouse = Warehouse::factory()->create([
        'name' => $name,
        'address' => null,
        'note' => $note,
    ]);

    Livewire::actingAs($user)
        ->test('livewire.warehouses.show', ['warehouse' => $warehouse])
        ->assertSee(e($name), false)
        ->assertSee(e($note), false)
        ->assertDontSee($name, false)
        ->assertDontSee($note, false);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/WarehousePagesTest.php --compact --filter=warehouse_show`

Expected: FAIL because the show route and component do not exist. `--filter=warehouse_show` may match none; if so, run the whole file and expect failures only in the newly added tests.

- [ ] **Step 3: Implement the show page**

Add this route after the index route inside the auth group in `routes/web.php`:

```php
Route::livewire('/warehouses/{warehouse}', 'livewire.warehouses.show')
    ->whereNumber('warehouse')
    ->name('warehouses.show');
```

Create `resources/views/components/livewire/warehouses/⚡show.blade.php` following `catalog/products/⚡show.blade.php`: `WithPagination`, `public Warehouse $warehouse`, `mount(Warehouse $warehouse): void`, `#[Computed]` methods with the exact signatures from Interfaces, breadcrumb link to `warehouses.index`, `x-page-header` with active/inactive badge, detail card using `—` for null `address`/`note`, summary values using `number_format($this->salesTotal, 2)`, sales table with columns document number, invoice date, customer, total, status, invoice link, `wire:key="warehouse-sale-{{ $sale->id }}"`, empty state `warehouses.no_sales`, and pagination links.

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/WarehousePagesTest.php --compact`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add routes/web.php resources/views/components/livewire/warehouses/⚡show.blade.php tests/Feature/WarehousePagesTest.php
git commit -m "feat(warehouses): add warehouse sales page"
```

### Task 6: Sidebar navigation and final verification

**Files:**
- Modify: `lang/en/dashboard.php`
- Modify: `resources/views/components/layout/sidebar.blade.php`
- Test: `tests/Feature/WarehousePagesTest.php`

**Interfaces:**
- Consumes: named route `warehouses.index` and lang key `dashboard.nav_warehouses`.
- Produces: sidebar entry after Products and before Sales with `request()->routeIs('warehouses.*')` active state and `wire:navigate`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/WarehousePagesTest.php`:

```php
test('sidebar links to the warehouses index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertSee(route('warehouses.index'))
        ->assertSee(__('dashboard.nav_warehouses'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/WarehousePagesTest.php --compact --filter=sidebar_links_to_the_warehouses_index`

Expected: FAIL because the nav label and sidebar item do not exist.

- [ ] **Step 3: Implement navigation**

Add `'nav_warehouses' => 'Warehouses',` to `lang/en/dashboard.php` after `nav_products`.

Add a sidebar array item after Products and before Sales with `label => __('dashboard.nav_warehouses')`, `href => route('warehouses.index')`, `active => request()->routeIs('warehouses.*')`, `navigate => true`, and a building-office outline icon path consistent with neighboring entries.

- [ ] **Step 4: Run focused tests, formatting, and static analysis**

Run: `vendor/bin/pest tests/Feature/WarehousePagesTest.php --compact`

Expected: PASS.

Run: `vendor/bin/pint --dirty --format agent`

Expected: PHP files formatted; no errors.

Run: `vendor/bin/phpstan analyse --no-progress`

Expected: no errors.

- [ ] **Step 5: Run the full test suite**

Run: `vendor/bin/pest --compact`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add lang/en/dashboard.php resources/views/components/layout/sidebar.blade.php tests/Feature/WarehousePagesTest.php
git commit -m "feat(warehouses): add sidebar navigation"
```
