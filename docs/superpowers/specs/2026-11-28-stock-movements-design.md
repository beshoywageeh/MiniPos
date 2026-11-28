# Stock Movements Module — Design

- **Date:** 2026-11-28
- **Status:** Approved design, pending spec review
- **Scope owner:** MiniPOS admin area

## Objective

Give the shop admin a usable stock movement module in phased deliveries. Phase 1 delivers the foundation needed to audit why stock changed:

1. Browse all `IN`/`OUT` stock movements across products and warehouses with filters and source links.
2. Record manual stock adjustments as first-class documents with document numbers, structured reasons, override auditing, and a resulting balance snapshot.
3. Print a saved stock adjustment.
4. Keep sales, purchases, and demo-data stock writes on the same movement-recording path so new movements carry a balance snapshot.

## Non-goals

- Warehouse transfers, opening-stock imports, and stock-take batches.
- Stock reports, exports, and running-balance reporting screens.
- Editing or deleting movements, adjustments, sales, or purchases.
- Changes to sales/purchase totals, document numbering for existing document types, or invoice previews beyond linking from the movement ledger.
- Backfilling `balance_after` for historical movement rows.
- Printable documents for return types or legacy `adjustment` movements.
- New role/permission system; all routes remain behind the existing `auth` middleware.

## Module phases

### Phase 1 — this spec and its implementation plan

Global movement ledger, `stock_adjustments` documents, printable adjustment preview, override flag, balance-after snapshots, and shared movement recorder.

### Later phases — not part of this plan

- **Phase 2:** warehouse transfers as paired `OUT`/`IN` movements with a `stock_transfers` document.
- **Phase 3:** stock reports by product/warehouse, low-stock summaries, and optional export.

## Conventions

- Livewire 4 single-file components (`⚡` prefix) under `resources/views/components/livewire/`, registered with `Route::livewire()`.
- Mirror the purchases/sales index pattern: `WithPagination`, `#[Url]` filters, `#[Computed]` query properties, shared `x-page-header`, `x-card`, `x-table`, `x-form.*`, `x-badge`, and `x-button` components, and `wire:key` on repeated rows.
- Use `DB::transaction()` for every save that creates a document and its movement.
- Use `DocumentNumber::next()` for adjustment numbers; never generate numbers inside a component.
- English copy in `lang/en/stock_movements.php`, `lang/en/stock_adjustments.php`, an enum lang file for `StockAdjustmentReason`, and a new `nav_stock_movements` key in `lang/en/dashboard.php`.
- Prefer named routes and `route()` for all links.
- Pest feature tests with factories and `Livewire::actingAs()`; TDD: failing tests first, then implementation, rerun focused tests after each change.
- Run `vendor/bin/pint --dirty --format agent`, `pnpm run build`, `vendor/bin/phpstan analyse --no-progress`, and `vendor/bin/pest --compact` before completion.

---

## A. Schema and support classes

### New table: `stock_adjustments`

| Column | Definition |
| --- | --- |
| `id` | Primary key |
| `document_number` | Unique string; generated with `DocumentNumber::next('stock_adjustment')` |
| `product_id` | Foreign key to `products` |
| `warehouse_id` | Foreign key to `warehouses` |
| `movement_type` | Enum `in`,`out` |
| `quantity` | Decimal(15,3), positive |
| `unit_cost` | Decimal(15,2), default `0` |
| `reason` | String backed by `StockAdjustmentReason` |
| `note` | Nullable text |
| `is_override` | Boolean, default `false` |
| `movement_date` | Date |
| `created_by` | Nullable foreign key to `users`, `nullOnDelete` |
| `timestamps` | Standard created/updated timestamps |

Create `app/Models/StockAdjustment.php` with `HasFactory`, casts for `movement_type`, `reason`, `is_override`, and `movement_date`, plus `product()`, `warehouse()`, `creator()`, and `movement()` relations. Create its factory and seeder with `php artisan make:model --factory --seed`. The seeder is not wired into `DatabaseSeeder` in phase 1.

Add `app/Enums/StockAdjustmentReason.php` with TitleCase cases: `CountCorrection`, `OpeningStock`, `Damage`, `Expiry`, `Other`. Add `label()` using `lang/en/StockAdjustmentReason.php`, matching the existing enum lang convention.

### Changes to `stock_movements`

Add nullable `balance_after` decimal(15,3). Add boolean `is_override` default `false`. Add an index on `(product_id, warehouse_id, movement_date, id)` for ledger filtering and stock lookups. Existing rows keep `NULL` balances and `false` overrides; there is no data backfill.

For new adjustment movements, `is_override` on both `stock_adjustments` and `stock_movements` is set from the same validated checkbox in the same transaction, so the ledger badge can be read directly from the movement.

### Document sequence

Add an idempotent migration that inserts the `stock_adjustment` sequence when absent: prefix `STADJ`, `next_number = 1`, `padding = 6`, `reset_period = monthly`. Its `down()` removes that row. Also add `seedDocumentSequence('stock_adjustment', 'STADJ')` to `TestSeeder`.

Extend `DocumentNumber::isNumberTaken()` so the `stock_adjustment` document type checks `StockAdjustment::class` for existing numbers. The document sequences page needs no component change; it displays `str($sequence->document_type)->headline()`.

---

## B. Shared movement recorder

Add `app/Support/StockMovementRecorder.php` (or equivalently named service/action) with a method that records one movement and returns it. Inputs: product, warehouse, `MovementType`, quantity, unit cost, reference type/id, note, movement date, creator id, and optional override flag.

Behavior:

1. Run inside `DB::transaction()`.
2. Lock existing `stock_movements` rows for the same product and warehouse with `lockForUpdate()`.
3. Compute the signed stock delta (`+quantity` for `IN`, `-quantity` for `OUT`).
4. Compute `balance_after` as the signed sum of all movements for that product/warehouse plus the new delta.
5. Create the movement with `balance_after` and `is_override`.

Use this recorder in:

- `livewire.sales.create` for each physical sold line, replacing the direct `StockMovement::query()->create()` call.
- `livewire.purchase.create` for each physical received line, replacing the direct create call.
- The new adjustment action.
- The four movement writes in `DemoDataSeeder` (purchase, sale, purchase return, sales return), so demo data carries balances. Do not seed adjustment records in phase 1.

The recorder does not change document totals or payment behavior. It does not backfill history.

---

## C. Routes and component layout

Add these routes inside the existing `auth` group in `routes/web.php`:

```php
Route::livewire('/stock-movements', 'livewire.stock-movements.index')->name('stock-movements.index');
Route::livewire('/stock-movements/create', 'livewire.stock-movements.create')->name('stock-movements.create');
Route::get('/stock-adjustments/{stockAdjustment}/preview', StockAdjustmentPreviewController::class)
    ->whereNumber('stockAdjustment')
    ->name('stock-adjustments.preview');
```

New files:

- `resources/views/components/livewire/stock-movements/⚡index.blade.php`
- `resources/views/components/livewire/stock-movements/⚡create.blade.php`
- `resources/views/components/livewire/catalog/products/actions/⚡adjust.blade.php` (modified)
- `app/Http/Controllers/StockAdjustmentPreviewController.php`
- `resources/views/stock-adjustments/preview.blade.php`
- `app/Support/StockMovementRecorder.php`
- `app/Support/StockMovementSource.php`
- `app/Enums/StockAdjustmentReason.php`
- `app/Models/StockAdjustment.php`
- `database/factories/StockAdjustmentFactory.php`
- `database/seeders/StockAdjustmentSeeder.php`
- `lang/en/stock_movements.php`
- `lang/en/stock_adjustments.php`
- `lang/en/StockAdjustmentReason.php`
- `tests/Feature/StockMovementsIndexTest.php`
- `tests/Feature/StockAdjustmentsCreateTest.php`
- `tests/Feature/StockAdjustmentPreviewTest.php`

The existing empty `app/Http/Controllers/StockMovementController.php` stays untouched.

---

## D. Movement ledger index

`livewire.stock-movements.index` uses `WithPagination` and `#[Url]` filters: `search` (product name or code), `warehouse`, `movementType`, `dateFrom`, and `dateTo`. Bind `search` with `wire:model.live.debounce.300ms`; bind selects/dates with `wire:model.live`.

Query: `StockMovement::query()->with(['product', 'warehouse', 'creator'])` with filters, `latest('movement_date')->latest('id')->paginate(10)`.

Columns:

- Date (`movement_date`)
- Source document (resolved by `StockMovementSource`, linked when a preview exists)
- Product name/code
- Warehouse
- Direction badge (`IN` emerald, `OUT` amber)
- Quantity, 3 decimals
- Unit cost, 2 decimals
- Balance after, 3 decimals or `—` when null
- Reason/source label
- Creator name or `—`
- Note or `—`
- Override badge when `is_override`

Empty state uses `stock_movements.no_records`.

`StockMovementSource` returns an array shape `{label: string, url: ?string}`:

| `reference_type` | Label | URL |
| --- | --- | --- |
| `Sale::class` | Sale document number | `sales.invoice_preview` |
| `Purchase::class` | Purchase document number | `purchases.invoice_preview` |
| `StockAdjustment::class` | Adjustment document number | `stock-adjustments.preview` |
| `SalesReturn::class` | Sales return label | none |
| `PurchaseReturn::class` | Purchase return label | none |
| `'adjustment'` | Adjustment label | none |
| `null` / unknown | `—` | none |

Missing referenced rows fall back to the label with no URL. User-provided product names and notes are escaped by Blade as in existing tables.

---

## E. New adjustment form and product-page delegation

`livewire.stock-movements.create` renders a dedicated page with these fields and rules:

| Field | Rules / behavior |
| --- | --- |
| `productId` | Required, integer, `exists:products,id`; options limited to active `ProductType::Product` products |
| `warehouseId` | Required, integer, `exists:warehouses,id`; all warehouses ordered by name |
| `movementType` | Required, `Rule::in(MovementType::cases() values)`; defaults to `IN` |
| `quantity` | Required, numeric, `min:0.001` |
| `unitCost` | Nullable numeric, `min:0`; empty becomes `0` |
| `reason` | Required, `Rule::in(StockAdjustmentReason::cases() values)` |
| `note` | Nullable string; required when override is checked |
| `movementDate` | Required date; defaults to today |
| `isOverride` | Boolean; only meaningful for `OUT` |

Save flow:

1. Validate. For `OUT`, compute the available stock from the signed movement total for product/warehouse.
2. If `quantity > available` and `isOverride` is false, add a `quantity` error using `stock_adjustments.insufficient_stock`. If override is true, require a note via validation.
3. In one transaction, create `StockAdjustment` with `DocumentNumber::next('stock_adjustment')` and all fields, then call `StockMovementRecorder` with `reference_type = StockAdjustment::class` and `reference_id = adjustment.id`.
4. Redirect with `redirectRoute('stock-adjustments.preview', ['stockAdjustment' => $adjustment], navigate: true)`.

The existing `livewire.catalog.products.actions.adjust` modal keeps its trigger and placement on the product page, but adds product-fixed reason/override handling and calls the same adjust action. Its scope stays the current product; it does not add a product selector.

---

## F. Printable adjustment preview

`StockAdjustmentPreviewController::__invoke(StockAdjustment $stockAdjustment): View` loads `product`, `warehouse`, `creator`, and `movement`, reads `Settings::all()`, and passes:

- `adjustment`
- `store` with name, address, and phone from settings, falling back to `config('app.name')`
- `invoiceFooter` from `invoice_footer`, falling back to `stock_adjustments.default_footer`

`resources/views/stock-adjustments/preview.blade.php` mirrors `purchases/invoice-preview.blade.php`: A4/thermal paper toggle, print button, store header, document number, product, warehouse, direction, reason, quantity, unit cost, balance after, override status, note, creator, and footer. Missing ids return 404 through route-model binding.

---

## G. Navigation and language

Add a sidebar item after Purchases in `resources/views/components/layout/sidebar.blade.php` using `route('stock-movements.index')`, `request()->routeIs('stock-movements.*')`, `wire:navigate`, `dashboard.nav_stock_movements`, and a new Heroicons-style outline path.

`lang/en/stock_movements.php`: title, subtitle, list title/subtitle, filter labels, all column labels, direction labels, empty state, source labels, and balance placeholder.

`lang/en/stock_adjustments.php`: form title/subtitle, field labels, buttons, validation messages including `insufficient_stock` and `override_note_required`, print labels, paper-size labels, footer copy, and redirect flash message.

`lang/en/StockAdjustmentReason.php`: labels matching the enum cases.

Do not rename or remove existing `products.*` movement/adjustment keys; the product modal continues to use them where they still apply.

---

## H. Tests

### `tests/Feature/StockMovementsIndexTest.php`

1. Guest is redirected from `stock-movements.index`.
2. Index lists date, source link, product, warehouse, direction, quantity, unit cost, balance after, reason/source, creator, note, and override badge.
3. Product/code search filters rows.
4. Warehouse filter shows only that warehouse.
5. Direction filter shows only the selected direction.
6. Date-range filter includes/excludes movements by `movement_date`.
7. Index paginates at 10 and orders newest first.
8. Sale and purchase sources link to their invoice previews.
9. Adjustment source links to the printable preview; returns use labels only; legacy `adjustment` source shows the adjustment label.
10. Product names and notes containing HTML are escaped.

### `tests/Feature/StockAdjustmentsCreateTest.php`

11. Guest is redirected from `stock-movements.create`.
12. Form renders today's date, `IN` default, active physical product options, reason options, and all warehouses.
13. Empty required payload produces validation errors and creates no adjustment or movement.
14. `IN` save creates an adjustment with document number, reason, note, override `false`, and one linked `IN` movement with `balance_after` and `is_override = false`.
15. `OUT` save with sufficient stock succeeds and produces a negative signed balance.
16. `OUT` save exceeding available stock fails without override and creates nothing.
17. `OUT` override exceeding available stock requires a note.
18. Override save sets `is_override` on both adjustment and movement.
19. Service products are rejected.
20. Save redirects to `stock-adjustments.preview`.
21. The document number uses the configured `stock_adjustment` sequence.
22. The product-page adjustment modal also creates a `StockAdjustment` document and linked movement.

### `tests/Feature/StockAdjustmentPreviewTest.php`

23. Guest is redirected from the preview.
24. Preview renders document number, product, warehouse, direction, reason, quantity, unit cost, balance after, override status, note, creator, store settings, and footer.
25. Missing adjustment id returns 404.

### Recorder coverage

26. Existing sale and purchase creation tests assert new movements have non-null `balance_after`.
27. `DatabaseSeedingTest` asserts seeded sale/purchase/return movements have non-null `balance_after`.
28. A document-number test asserts `stock_adjustment` numbers are checked against `StockAdjustment::class`.

### Verification

- `vendor/bin/pest tests/Feature/StockMovementsIndexTest.php tests/Feature/StockAdjustmentsCreateTest.php tests/Feature/StockAdjustmentPreviewTest.php --compact`
- `vendor/bin/pest --compact`
- `vendor/bin/pint --dirty --format agent`
- `pnpm run build`
- `vendor/bin/phpstan analyse --no-progress`

The phase is complete only when all checks are green and a fresh review finds no unresolved Critical or Important findings.
