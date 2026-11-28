# Stock Movements Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver phase 1 of the stock movements module: a global ledger, first-class stock adjustments with document numbers and printable detail, override auditing, and balance-after snapshots on new movements.

**Architecture:** Add adjustment documents and a shared `StockMovementRecorder`; sales, purchases, adjustments, and demo-data writes all use the recorder so `balance_after` is computed consistently. Livewire 4 SFCs provide the ledger, new adjustment form, and product-page adjustment shortcut, while a source resolver turns polymorphic references into labels and preview links.

**Tech Stack:** Laravel 12 / PHP 8.5, Livewire 4 single-file components, Tailwind v4, Alpine.js, Pest, PHPStan, Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-11-28-stock-movements-design.md`

## Global Constraints

- Livewire 4 single-file components use the `⚡` prefix and live under `resources/views/components/livewire/`.
- All new routes are inside the existing `auth` middleware group and use named routes.
- `MovementType` values are exactly `in` and `out`; quantity uses 3 decimals; unit cost uses 2 decimals.
- `StockAdjustmentReason` cases are exactly `CountCorrection`, `OpeningStock`, `Damage`, `Expiry`, `Other` with TitleCase keys.
- Adjustment document type is exactly `stock_adjustment`; prefix `STADJ`; padding `6`; reset period `monthly`.
- Ledger pagination is exactly 10 rows per page.
- New copy goes in `lang/en/stock_movements.php`, `lang/en/stock_adjustments.php`, `lang/en/StockAdjustmentReason.php`, and `lang/en/dashboard.php`; existing `products.*` keys remain.
- Use `DB::transaction()` for document-plus-movement saves and `DocumentNumber::next()` for adjustment numbers.
- Use Pest feature tests with factories and `Livewire::actingAs()`; write failing tests before implementation.
- Run all Artisan commands with `--no-interaction`.
- Before completion run `vendor/bin/pint --dirty --format agent`, `pnpm run build`, `vendor/bin/phpstan analyse --no-progress`, and `vendor/bin/pest --compact`.

## Review Focus

- **Balance derivation:** a movement after mixed `IN`/`OUT` rows must use the signed total, not the previous row; pinned by Task 2's `recorder computes balance_after from signed stock total`.
- **Override audit:** an insufficient `OUT` override must require a note and set the flag on both adjustment and movement; pinned by Task 3's `override cannot be used without a note` and `override records the flag on adjustment and movement`.
- **Legacy/unknown sources:** old `'adjustment'` rows and null references must not break the ledger; pinned by Task 7's `legacy and unknown sources render safe labels`.
- **User-supplied text:** product names and notes containing HTML must be escaped; pinned by Task 7's `user-supplied text is escaped`.
- **Non-stock products:** service products must be rejected by the adjustment action and omitted from the global product selector; pinned by Task 3's `service products cannot be adjusted` and Task 4's `form offers only active physical products`.

---

### Task 1: Schema, adjustment model, enum, and document sequence

**Files:**
- Create: `app/Enums/StockAdjustmentReason.php`
- Create: `lang/en/StockAdjustmentReason.php`
- Create: `app/Models/StockAdjustment.php`
- Create: `database/factories/StockAdjustmentFactory.php`
- Create: `database/seeders/StockAdjustmentSeeder.php`
- Create: `database/migrations/2026_11_28_000001_create_stock_adjustments_table.php`
- Create: `database/migrations/2026_11_28_000002_add_balance_after_and_override_to_stock_movements.php`
- Create: `database/migrations/2026_11_28_000003_seed_stock_adjustment_sequence.php`
- Create: `tests/Feature/StockAdjustmentSchemaTest.php`
- Modify: `app/Support/DocumentNumber.php`
- Modify: `database/seeders/TestSeeder.php`

**Interfaces:**
- Consumes: existing `DocumentSequence`, `DocumentNumber`, `ResetPeriod`, and `StockMovement` behavior.
- Produces:
  - `StockAdjustmentReason::{CountCorrection, OpeningStock, Damage, Expiry, Other}` with `label(): string`.
  - `StockAdjustment` with casts `movement_type: MovementType`, `reason: StockAdjustmentReason`, `is_override: boolean`, `movement_date: date`, and relations `product()`, `warehouse()`, `creator()`, `movement()` (`morphOne(StockMovement::class, 'reference')`).
  - `StockAdjustmentFactory` defaults: physical product, warehouse, `MovementType::IN`, quantity `1`, unit cost `0`, reason `CountCorrection`, note `null`, `is_override` `false`, movement date today, user creator, and unique `document_number` shaped `STADJ-######`.
  - `DocumentNumber::next('stock_adjustment')` returns `STADJ-000001` on a fresh sequence and skips numbers already used by `StockAdjustment`.

- [ ] **Step 1: Write the failing schema/sequence tests**

Create `tests/Feature/StockAdjustmentSchemaTest.php` with `uses(RefreshDatabase::class)` and these tests:

```php
test('stock adjustment schema and sequence are available', function () {
    expect(Schema::hasColumns('stock_adjustments', [
        'document_number', 'product_id', 'warehouse_id', 'movement_type', 'quantity',
        'unit_cost', 'reason', 'note', 'is_override', 'movement_date', 'created_by',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('stock_movements', ['balance_after', 'is_override']))->toBeTrue();

    $sequence = DocumentSequence::query()->where('document_type', 'stock_adjustment')->firstOrFail();

    expect($sequence->prefix)->toBe('STADJ')
        ->and($sequence->padding)->toBe(6)
        ->and($sequence->reset_period)->toBe(ResetPeriod::MONTHLY);
});

test('stock adjustment numbers skip numbers already used by adjustments', function () {
    $sequence = DocumentSequence::query()->where('document_type', 'stock_adjustment')->firstOrFail();
    $taken = DocumentNumber::preview($sequence);
    StockAdjustment::factory()->create(['document_number' => $taken]);

    expect(DocumentNumber::next('stock_adjustment'))->toBe('STADJ-000002')
        ->and($sequence->refresh()->next_number)->toBe(3);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/StockAdjustmentSchemaTest.php --compact`

Expected: FAIL because the table, columns, sequence, model, and enum do not exist.

- [ ] **Step 3: Create the migrations**

Use `php artisan make:migration create_stock_adjustments_table --no-interaction` and rename it to `2026_11_28_000001_create_stock_adjustments_table.php`. Define `document_number` unique, FKs to `products`, `warehouses`, and nullable `users`, enum `in,out`, decimals `(15,3)`/`(15,2)`, string `reason`, nullable text `note`, boolean `is_override` default false, date `movement_date`, and timestamps. `down()` drops the table.

Create `2026_11_28_000002_add_balance_after_and_override_to_stock_movements.php`: add nullable `balance_after` decimal `(15,3)`, boolean `is_override` default false, and index `(product_id, warehouse_id, movement_date, id)`. `down()` drops the index and columns.

Create `2026_11_28_000003_seed_stock_adjustment_sequence.php`: idempotently insert `document_type = stock_adjustment`, `prefix = STADJ`, `next_number = 1`, `padding = 6`, `reset_period = monthly`; `down()` deletes that row.

- [ ] **Step 4: Create the enum, model, factory, and seeder**

Use `php artisan make:model StockAdjustment --factory --seed --no-interaction`, then add the cast map and relations listed in Interfaces. Add `lang/en/StockAdjustmentReason.php` with one label per case. Add `seedDocumentSequence('stock_adjustment', 'STADJ')` to `database/seeders/TestSeeder.php`. The new seeder may create two factory adjustments; do not wire it into `DatabaseSeeder`.

- [ ] **Step 5: Extend document-number collision checking**

In `DocumentNumber::isNumberTaken()`, add `'stock_adjustment' => StockAdjustment::class` to the `match`.

- [ ] **Step 6: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/StockAdjustmentSchemaTest.php --compact`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Enums/StockAdjustmentReason.php lang/en/StockAdjustmentReason.php app/Models/StockAdjustment.php database/factories/StockAdjustmentFactory.php database/seeders/StockAdjustmentSeeder.php database/migrations/2026_11_28_000001_create_stock_adjustments_table.php database/migrations/2026_11_28_000002_add_balance_after_and_override_to_stock_movements.php database/migrations/2026_11_28_000003_seed_stock_adjustment_sequence.php tests/Feature/StockAdjustmentSchemaTest.php app/Support/DocumentNumber.php database/seeders/TestSeeder.php
git commit -m "feat: add stock adjustment schema and sequence"
```

---

### Task 2: Shared stock movement recorder

**Files:**
- Create: `app/Support/StockMovementRecorder.php`
- Modify: `resources/views/components/livewire/sales/⚡create.blade.php`
- Modify: `resources/views/components/livewire/purchase/⚡create.blade.php`
- Modify: `database/seeders/DemoDataSeeder.php`
- Modify: `tests/Feature/SalesCreateTest.php`
- Modify: `tests/Feature/PurchasesCreateTest.php`
- Modify: `tests/Feature/DatabaseSeedingTest.php`

**Interfaces:**
- Consumes: `StockMovement`, `MovementType`, existing sale/purchase movement writes.
- Produces:
  - `StockMovementRecorder::availableStock(Product $product, Warehouse $warehouse): float`
  - `StockMovementRecorder::record(array $attributes): StockMovement` where `$attributes` has shape `{product: Product, warehouse: Warehouse, movement_type: MovementType, quantity: string|float|int, unit_cost: string|float|int|null, reference_type: ?string, reference_id: ?int, note: ?string, movement_date: string, created_by: ?int, is_override?: bool}`.

- [ ] **Step 1: Add failing balance-after assertions to existing tests**

In `tests/Feature/SalesCreateTest.php`, extend the existing movement assertion after a saved sale:

```php
expect($movement->balance_after)->not->toBeNull();
```

In `tests/Feature/PurchasesCreateTest.php`, extend the existing `IN` movement assertion:

```php
expect($movement->balance_after)->not->toBeNull();
```

In `tests/Feature/DatabaseSeedingTest.php`, add:

```php
it('gives seeded stock movements a balance snapshot', function () {
    $this->seed(TestSeeder::class);
    $this->seed(DemoDataSeeder::class);

    expect(StockMovement::query()->whereNull('balance_after')->doesntExist())->toBeTrue();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/SalesCreateTest.php tests/Feature/PurchasesCreateTest.php tests/Feature/DatabaseSeedingTest.php --compact`

Expected: FAIL because no movement has `balance_after`.

- [ ] **Step 3: Implement the recorder**

Use `php artisan make:class Support/StockMovementRecorder --no-interaction`. Implement `availableStock()` as:

```php
return (float) StockMovement::query()
    ->where('product_id', $product->id)
    ->where('warehouse_id', $warehouse->id)
    ->selectRaw('COALESCE(SUM(CASE WHEN movement_type = ? THEN quantity ELSE -quantity END), 0) as available', [MovementType::IN->value])
    ->value('available');
```

Implement `record()` in one `DB::transaction()`: lock matching movements with `lockForUpdate()`, recompute `available`, compute `balance_after = available ± quantity`, then create the movement with `movement_type = $movementType->value`, the given reference data, and `is_override` defaulting to false.

- [ ] **Step 4: Route all movement writes through the recorder**

Replace each direct `StockMovement::query()->create([...])` in the sale create, purchase create, and the four `DemoDataSeeder` movement writes with `app(StockMovementRecorder::class)->record([...])`, preserving the existing product, warehouse, direction, quantity, unit cost, reference, date, and creator values. Sales use `MovementType::OUT`; purchases and returns use `MovementType::IN`.

- [ ] **Step 5: Add the signed-total recorder test**

Add to `tests/Feature/SalesCreateTest.php`:

```php
test('recorder computes balance_after from signed stock total', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $user = User::factory()->create();

    $recorder = app(StockMovementRecorder::class);

    $recorder->record([
        'product' => $product, 'warehouse' => $warehouse,
        'movement_type' => MovementType::IN, 'quantity' => '10',
        'unit_cost' => '0', 'reference_type' => null, 'reference_id' => null,
        'note' => null, 'movement_date' => today()->toDateString(), 'created_by' => $user->id,
    ]);
    $movement = $recorder->record([
        'product' => $product, 'warehouse' => $warehouse,
        'movement_type' => MovementType::OUT, 'quantity' => '3.5',
        'unit_cost' => '0', 'reference_type' => null, 'reference_id' => null,
        'note' => null, 'movement_date' => today()->toDateString(), 'created_by' => $user->id,
    ]);

    expect((float) $movement->balance_after)->toBe(6.5);
});
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/SalesCreateTest.php tests/Feature/PurchasesCreateTest.php tests/Feature/DatabaseSeedingTest.php --compact`

Expected: PASS, including the signed-total recorder test.

- [ ] **Step 7: Commit**

```bash
git add app/Support/StockMovementRecorder.php resources/views/components/livewire/sales/⚡create.blade.php resources/views/components/livewire/purchase/⚡create.blade.php database/seeders/DemoDataSeeder.php tests/Feature/SalesCreateTest.php tests/Feature/PurchasesCreateTest.php tests/Feature/DatabaseSeedingTest.php
git commit -m "feat: record balance snapshots for stock movements"
```

---

### Task 3: Stock adjustment action

**Files:**
- Create: `app/Support/RecordStockAdjustment.php`
- Create: `tests/Feature/RecordStockAdjustmentTest.php`
- Modify: `lang/en/stock_adjustments.php`

**Interfaces:**
- Consumes: `StockAdjustment`, `StockAdjustmentReason`, `StockMovementRecorder`, `DocumentNumber`, `MovementType`, `ProductType`.
- Produces: `RecordStockAdjustment::execute(array $attributes): StockAdjustment` where `$attributes` has shape `{product_id: int, warehouse_id: int, movement_type: MovementType, quantity: string|float|int, unit_cost?: string|float|int|null, reason: StockAdjustmentReason, note?: string|null, movement_date: string, is_override?: bool, created_by?: ?int}`.

- [ ] **Step 1: Write failing action tests**

Create `tests/Feature/RecordStockAdjustmentTest.php` with `uses(RefreshDatabase::class)` and tests named:

- `in adjustment creates a numbered document and linked movement`
- `out adjustment succeeds when stock is sufficient`
- `out adjustment beyond available stock is rejected`
- `override cannot be used without a note`
- `override records the flag on adjustment and movement`
- `service products cannot be adjusted`

Assert exact values: document numbers start `STADJ-`; `reference_type === StockAdjustment::class`; `movement_type === MovementType::IN->value` or `OUT->value`; `balance_after` is non-null; false/true override flags are copied to both records; insufficient stock throws `ValidationException` with a `quantity` key; override without note throws with a `note` key.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/RecordStockAdjustmentTest.php --compact`

Expected: FAIL because the action does not exist.

- [ ] **Step 3: Implement the action**

Use `php artisan make:class Support/RecordStockAdjustment --no-interaction`. Validate with `Validator::make()` using: `product_id` required integer exists, `warehouse_id` required integer exists, `movement_type` required `Rule::in`, `quantity` required numeric `min:0.001`, `unit_cost` nullable numeric `min:0`, `reason` required `Rule::in`, `note` nullable string, `movement_date` required date, `is_override` boolean. Reject products whose `type !== ProductType::Product`. For `OUT`, compare `quantity` with `StockMovementRecorder::availableStock()` and throw `ValidationException::withMessages(['quantity' => __('stock_adjustments.insufficient_stock')])` unless override is true. If override is true, require a non-empty note. In one `DB::transaction()`, create the adjustment with `DocumentNumber::next('stock_adjustment')` and call the recorder with `reference_type = StockAdjustment::class` and `reference_id = adjustment.id`.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/RecordStockAdjustmentTest.php --compact`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support/RecordStockAdjustment.php tests/Feature/RecordStockAdjustmentTest.php lang/en/stock_adjustments.php
git commit -m "feat: add stock adjustment action"
```

---

### Task 4: Global New Adjustment screen

**Files:**
- Create: `resources/views/components/livewire/stock-movements/⚡create.blade.php`
- Create: `tests/Feature/StockAdjustmentsCreateTest.php`
- Modify: `routes/web.php`
- Modify: `lang/en/stock_movements.php`
- Modify: `lang/en/stock_adjustments.php`

**Interfaces:**
- Consumes: `RecordStockAdjustment::execute()`, `StockAdjustmentReason`, `MovementType`, `Warehouse`.
- Produces: Livewire alias `livewire.stock-movements.create`, route name `stock-movements.create`, public properties `productId`, `warehouseId`, `movementType`, `quantity`, `unitCost`, `reason`, `note`, `movementDate`, `isOverride`, and method `save(): void`.

- [ ] **Step 1: Write failing component tests**

Create `tests/Feature/StockAdjustmentsCreateTest.php` with `uses(RefreshDatabase::class)` and tests named:

- `guests are redirected from the new adjustment form`
- `new adjustment form defaults dates and offers only active physical products`
- `empty new adjustment shows validation errors and saves nothing`
- `saving an adjustment redirects to its printable preview`
- `adjustment document numbers use the stock adjustment sequence`

Assert `movementDate === today()->format('Y-m-d')`, `movementType === MovementType::IN->value`, inactive and service products are absent from rendered options, reason options include all five `StockAdjustmentReason` labels, required errors appear, no records are created on validation failure, and a successful save redirects to `route('stock-adjustments.preview', $adjustment)` with a `STADJ-000001` document number.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/StockAdjustmentsCreateTest.php --compact`

Expected: FAIL because the route/component does not exist.

- [ ] **Step 3: Add the route and build the SFC**

Add inside `auth`:

```php
Route::livewire('/stock-movements/create', 'livewire.stock-movements.create')->name('stock-movements.create');
```

Create the SFC with `[Computed]` methods `productOptions()`, `warehouseOptions()`, `movementTypeOptions()`, and `reasonOptions()`. `productOptions()` uses `Product::query()->where('is_active', true)->where('type', ProductType::Product)->orderBy('name')->pluck('name','id')` with a blank prompt. `save()` validates the same rules as the action, calls `RecordStockAdjustment::execute()` with `Auth::id()`, then `redirectRoute('stock-adjustments.preview', ['stockAdjustment' => $adjustment], navigate: true)`. Render fields with existing `x-form.*` components; use `x-form.checkbox` for `isOverride`.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/StockAdjustmentsCreateTest.php --compact`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/livewire/stock-movements/⚡create.blade.php tests/Feature/StockAdjustmentsCreateTest.php routes/web.php lang/en/stock_movements.php lang/en/stock_adjustments.php
git commit -m "feat: add new stock adjustment screen"
```

---

### Task 5: Product-page adjustment delegates to the action

**Files:**
- Modify: `resources/views/components/livewire/catalog/products/actions/⚡adjust.blade.php`
- Modify: `tests/Feature/ProductStockAdjustmentTest.php`
- Modify: `lang/en/products.php`

**Interfaces:**
- Consumes: `RecordStockAdjustment::execute()`, `StockAdjustmentReason`, `MovementType`.
- Produces: the existing `livewire.catalog.products.actions.adjust` component now creates `StockAdjustment` documents and dispatches `product-adjusted` only after success.

- [ ] **Step 1: Rewrite the product adjustment tests to fail**

Update `tests/Feature/ProductStockAdjustmentTest.php` so the success tests assert:

```php
expect(StockAdjustment::query()->sole()->document_number)->toStartWith('STADJ-')
    ->and($movement->reference_type)->toBe(StockAdjustment::class)
    ->and($movement->reference_id)->toBe(StockAdjustment::query()->sole()->id)
    ->and($movement->is_override)->toBeFalse();
```

Add a test `product adjustment records the selected reason` setting `reason` to `StockAdjustmentReason::CountCorrection->value` and asserting both records use it. Add a test `product adjustment rejects insufficient stock unless overridden` matching Task 3 behavior.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/ProductStockAdjustmentTest.php --compact`

Expected: FAIL because the component still writes `reference_type = 'adjustment'` directly.

- [ ] **Step 3: Refactor the component**

Keep the existing `public Product $product`, modal trigger, flash event, and fields. Add `reason` and `isOverride` properties, add them to validation, and replace the direct `stockMovements()->create()` call with `RecordStockAdjustment::execute()` using `product_id = $this->product->id` and `created_by = Auth::id()`. On success, reset fields, reset `movementType` to `IN` and `movementDate` to today, then dispatch `product-adjusted`. Add translated labels for reason and override to `lang/en/products.php`; do not delete existing keys.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/ProductStockAdjustmentTest.php --compact`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/livewire/catalog/products/actions/⚡adjust.blade.php tests/Feature/ProductStockAdjustmentTest.php lang/en/products.php
git commit -m "feat: route product adjustments through shared action"
```

---

### Task 6: Printable adjustment preview

**Files:**
- Create: `app/Http/Controllers/StockAdjustmentPreviewController.php`
- Create: `resources/views/stock-adjustments/preview.blade.php`
- Create: `tests/Feature/StockAdjustmentPreviewTest.php`
- Modify: `routes/web.php`
- Modify: `lang/en/stock_adjustments.php`

**Interfaces:**
- Consumes: `StockAdjustment`, `Settings`, `x-print-layout`.
- Produces: route name `stock-adjustments.preview` and invokable controller returning a printable view.

- [ ] **Step 1: Write failing preview tests**

Create `tests/Feature/StockAdjustmentPreviewTest.php` with `uses(RefreshDatabase::class)` and tests named:

- `guests are redirected from the stock adjustment preview`
- `stock adjustment preview renders the saved document`
- `missing stock adjustment returns 404`

The render test must see the document number, product name, warehouse name, direction label, reason label, quantity, unit cost, balance after, override status, note, creator name, store name/address/phone, and footer copy.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/StockAdjustmentPreviewTest.php --compact`

Expected: FAIL because route/controller/view do not exist.

- [ ] **Step 3: Add the route, controller, and view**

Add inside `auth`:

```php
Route::get('/stock-adjustments/{stockAdjustment}/preview', StockAdjustmentPreviewController::class)
    ->whereNumber('stockAdjustment')
    ->name('stock-adjustments.preview');
```

Implement controller `__invoke(StockAdjustment $stockAdjustment): View` mirroring `PurchaseInvoicePreviewController`, loading `product`, `warehouse`, `creator`, `movement` and passing `adjustment`, `store`, and `footer` to the view. Implement the Blade view with the same A4/thermal toggle and print button pattern as `purchases/invoice-preview.blade.php`, using new `stock_adjustments.*` copy only.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/StockAdjustmentPreviewTest.php --compact`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/StockAdjustmentPreviewController.php resources/views/stock-adjustments/preview.blade.php tests/Feature/StockAdjustmentPreviewTest.php routes/web.php lang/en/stock_adjustments.php
git commit -m "feat: add printable stock adjustment preview"
```

---

### Task 7: Global movement ledger and navigation

**Files:**
- Create: `app/Support/StockMovementSource.php`
- Create: `resources/views/components/livewire/stock-movements/⚡index.blade.php`
- Create: `tests/Feature/StockMovementsIndexTest.php`
- Modify: `routes/web.php`
- Modify: `resources/views/components/layout/sidebar.blade.php`
- Modify: `lang/en/dashboard.php`
- Modify: `lang/en/stock_movements.php`

**Interfaces:**
- Consumes: `StockMovement`, `Sale`, `Purchase`, `StockAdjustment`, `SalesReturn`, `PurchaseReturn`, route names from earlier tasks.
- Produces:
  - `StockMovementSource::describe(StockMovement $movement): array` with shape `{label: string, url: ?string}`.
  - Livewire alias `livewire.stock-movements.index`, route name `stock-movements.index`, public properties `search`, `warehouse`, `movementType`, `dateFrom`, `dateTo`, and computed `movements()`.

- [ ] **Step 1: Write failing ledger tests**

Create `tests/Feature/StockMovementsIndexTest.php` with `uses(RefreshDatabase::class)` and tests named:

- `guests are redirected from the movement ledger`
- `ledger lists all movement columns`
- `product and code search filters movements`
- `warehouse filter shows only that warehouse`
- `direction filter shows only the selected direction`
- `date range filter includes and excludes movements`
- `ledger paginates at ten and orders newest first`
- `sale and purchase sources link to invoice previews`
- `adjustment source links to the printable preview`
- `legacy and unknown sources render safe labels`
- `null balances render a placeholder`
- `user-supplied text is escaped`

Use `StockMovement::factory()` with explicit `created_by`, `reference_type`, and `reference_id`. For escaping, set product name and note to `<script>alert(1)</script>` and assert the raw string is not present while the escaped form is.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/StockMovementsIndexTest.php --compact`

Expected: FAIL because route/component/source resolver do not exist.

- [ ] **Step 3: Implement the source resolver**

Use `php artisan make:class Support/StockMovementSource --no-interaction`. `describe()` returns:

- `Sale::class`: sale's `document_number`, URL `sales.invoice_preview` when the referenced sale exists.
- `Purchase::class`: purchase's `document_number`, URL `purchases.invoice_preview` when it exists.
- `StockAdjustment::class`: adjustment's `document_number`, URL `stock-adjustments.preview` when it exists.
- `SalesReturn::class` and `PurchaseReturn::class`: translated label, no URL.
- `'adjustment'`: translated adjustment label, no URL.
- `null` or anything else: `—`, no URL.

If a referenced model is missing, keep the label and set URL to null.

- [ ] **Step 4: Implement the ledger SFC**

Add route:

```php
Route::livewire('/stock-movements', 'livewire.stock-movements.index')->name('stock-movements.index');
```

Create the SFC with `WithPagination` and `#[Url]` properties. `movements()` queries `StockMovement::query()->with(['product','warehouse','creator'])`, applies `whereHas('product')` for name/code search, exact warehouse/direction filters, inclusive `whereBetween('movement_date', [$dateFrom, $dateTo])`, and `latest('movement_date')->latest('id')->paginate(10)`. Render the columns from the spec, using `StockMovementSource::describe()` for the source cell, `x-badge` for direction and override, `—` for null balances, and `wire:key` on rows.

- [ ] **Step 5: Add navigation**

Add `nav_stock_movements` to `lang/en/dashboard.php` and a sidebar item immediately after Purchases using `route('stock-movements.index')`, `request()->routeIs('stock-movements.*')`, `wire:navigate`, and a new Heroicons outline path.

- [ ] **Step 6: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/StockMovementsIndexTest.php --compact`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Support/StockMovementSource.php resources/views/components/livewire/stock-movements/⚡index.blade.php tests/Feature/StockMovementsIndexTest.php routes/web.php resources/views/components/layout/sidebar.blade.php lang/en/dashboard.php lang/en/stock_movements.php
git commit -m "feat: add stock movement ledger"
```

---

### Task 8: Full verification

**Files:**
- None; verification only.

**Interfaces:**
- Consumes: all files from Tasks 1–7.
- Produces: green focused suites, green full suite, clean formatting, clean static analysis, and built frontend assets.

- [ ] **Step 1: Run focused suites**

Run:

```bash
vendor/bin/pest tests/Feature/StockAdjustmentSchemaTest.php tests/Feature/RecordStockAdjustmentTest.php tests/Feature/StockAdjustmentsCreateTest.php tests/Feature/ProductStockAdjustmentTest.php tests/Feature/StockAdjustmentPreviewTest.php tests/Feature/StockMovementsIndexTest.php tests/Feature/SalesCreateTest.php tests/Feature/PurchasesCreateTest.php tests/Feature/DatabaseSeedingTest.php tests/Feature/DocumentNumberTest.php --compact
```

Expected: PASS.

- [ ] **Step 2: Run project verification**

Run:

```bash
vendor/bin/pint --dirty --format agent
pnpm run build
vendor/bin/phpstan analyse --no-progress
vendor/bin/pest --compact
```

Expected: Pint reports no unresolved style issues, Vite build succeeds, PHPStan reports 0 errors, and the full Pest suite passes.

- [ ] **Step 3: Commit any verification-only fixes**

```bash
git add -A
git commit -m "style: apply stock movements verification fixes"
```

Skip this commit if verification produced no changes.
