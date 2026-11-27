# Purchases — Design

- **Date:** 2026-11-27
- **Status:** Approved design, pending spec review
- **Scope owner:** MiniPOS admin area

## Objective

Give the shop admin the first usable Purchases area, following the established sales pattern:

1. Browse purchases with search and warehouse filtering.
2. Create a purchase from a supplier, warehouse, and item lines, recording stock received and any money paid.
3. Open a saved purchase as a printable invoice document.
4. Support saving a purchase as a draft without stock or payment side effects.

## Non-goals

- Purchase returns screens or returns workflow.
- Standalone payments and expenses screens.
- Editing or deleting purchases.
- Changes to reports, the demo data seeder, or the sales flow beyond linking from party history as described in section E.
- New product/cashbox/warehouse setup screens.

## Conventions

- Livewire 4 single-file components (`⚡` prefix) under `resources/views/components/livewire/`, registered with `Route::livewire()`.
- Mirror `livewire.sales.index` / `livewire.sales.create` structure: computed properties, `WithPagination`, `#[Url]` filters, shared Blade components (`x-page-header`, `x-card`, `x-table`, `x-form.*`, `x-badge`, `x-button`), `wire:key` on repeated rows.
- Use `DB::transaction()` for every save that creates documents, item rows, stock movements, and payments.
- Use `DocumentNumber::next()` for purchase and payment numbers; never generate numbers in the component.
- English copy in `lang/en/purchases.php`; use existing `DocumentStatus` and `PaymentType` lang files for enum labels.
- Named routes and `route()` for all links.
- Pest feature tests with `User::factory()`, `Party::factory()`, `Warehouse::factory()`, `Product::factory()`, `Purchase::factory()`, and `Cashbox::factory()`; TDD: failing tests first, then implementation, rerun focused tests after each change.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes, `pnpm run build` for frontend changes, and `vendor/bin/phpstan analyse --no-progress` before completion.

---

## A. Routes and component layout

Add these routes inside the existing `auth` group in `routes/web.php`:

```php
Route::livewire('/purchases', 'livewire.purchase.index')->name('purchases.index');
Route::livewire('/purchases/create', 'livewire.purchase.create')->name('purchases.create');
Route::get('/purchases/{purchase}/invoice-preview', PurchaseInvoicePreviewController::class)
    ->whereNumber('purchase')
    ->name('purchases.invoice_preview');
```

New files:

- `resources/views/components/livewire/purchase/⚡index.blade.php`
- `resources/views/components/livewire/purchase/⚡create.blade.php`
- `app/Http/Controllers/PurchaseInvoicePreviewController.php`
- `resources/views/purchases/invoice-preview.blade.php`
- `lang/en/purchases.php`
- `tests/Feature/PurchasesCreateTest.php`
- `tests/Feature/PurchasesIndexTest.php`
- `tests/Feature/PurchaseInvoicePreviewTest.php`
- `database/migrations/2026_11_27_*_add_draft_to_purchases_status.php`

The existing empty `app/Http/Controllers/PurchaseController.php` and all existing purchase models/migrations stay untouched except for the new status migration. No new factory or seeder changes are needed; factories already exist for `Purchase`, `PurchaseItem`, `PurchaseReturn`, and `Payment`.

## B. Status migration

`purchases.status` is currently a DB enum of `pending|paid|partial`. Add a migration that widens it via `->change()`:

```php
Schema::table('purchases', function (Blueprint $table): void {
    $table->enum('status', ['draft', 'pending', 'paid', 'partial'])
        ->default('pending')
        ->change();
});
```

- Keeps a DB-level constraint instead of downgrading the column to a plain string.
- Works on the project's SQLite setup (Laravel rebuilds the table from the new column state) and on MySQL/PostgreSQL via native enum modification.
- Existing values remain valid; no data backfill is required.
- `Purchase` already casts `status` to `DocumentStatus`, which includes `DRAFT`.
- A test asserts a draft purchase persists with `status = draft` and that a non-draft purchase with no payment still persists as `pending`.

## C. New Purchase form

`livewire.purchase.create` mirrors `livewire.sales.create` (item search box, item lines, computed subtotal/discount/tax/total/paid/due) with these differences.

### Fields and validation

| Field | Rules / behavior |
| --- | --- |
| `partyId` (Supplier) | Required, integer, `exists:parties,id`. Options restricted to parties whose `type` is `supplier` or `both`. |
| `warehouseId` | Required, integer, `exists:warehouses,id`. Options: active warehouses only. Preselect the sole active warehouse in `mount()`, or include a blank prompt when multiple exist. |
| `invoiceDate` | Required, date; defaults to today. |
| `dueDate` | Nullable, date, `after_or_equal:invoiceDate`; defaults to today + 14 days. |
| `notes` | Nullable string. |
| `isDraft` | Boolean checkbox. |
| `items` | Required array, min 1. Each line: `product_id` required integer `exists:products,id`; `quantity` required numeric min 0.001; `unit_price` required numeric min 0; `discount_amount` nullable numeric min 0; `tax_rate` nullable numeric min 0. |
| `paidAmount` | Nullable numeric min 0. Cannot exceed grand total on a non-draft. When `isDraft` is checked the entered amount is ignored and stored as 0. |
| `paymentMethod` | Required only when non-draft and `paidAmount > 0`; must be a `PaymentMethod` value; defaults to `cash`. |
| `cashboxId` | Required only when non-draft and `paidAmount > 0`; integer `exists:cashboxes,id`. Preselect the sole active cashbox in `mount()`, or include a blank prompt when multiple exist. |

### Product search

- `updatedSearchTerm()` uses `ProductMatcher::search()` exactly as the sale form does.
- Search results map `purchase_price` (not `selling_price`) into the result array and display it.
- `addProductFromSearch()` rejects inactive products and pre-fills `unit_price` from `purchase_price`.
- Products of type `service` may be added as lines but do not create stock movements.

### Save transaction

`save()` validates, checks `paidAmount <= total`, then runs one `DB::transaction()`:

1. Create `Purchase` with `document_number = DocumentNumber::next('purchase')`, supplier, warehouse, dates, computed totals, `paid_amount = $isDraft ? 0 : paidFloat()`, `remaining_amount = total - paid`, `created_by = Auth::id()`, and derived status:
   - draft → `DRAFT`
   - paid >= total → `PAID`
   - paid > 0 → `PARTIAL`
   - otherwise → `PENDING`
2. Create `purchase_items` rows with `product_name`, `sku`, quantity, unit price, discount amount, tax amount, and line total.
3. If draft, return the purchase immediately — no stock movements and no payment.
4. For each non-service item, create a `StockMovement` with `movement_type = IN`, `quantity`, `unit_cost = unit_price`, `reference_type = Purchase::class`, `reference_id = purchase.id`, `movement_date = invoice_date`, and `created_by`.
5. If paid > 0, create a `Payment` with `DocumentNumber::next('payment')`, supplier `party_id`, `cashbox_id`, `purchase_id`, `type = payment`, `method`, `amount`, `payment_date = invoice_date`, and `created_by`.
6. Redirect with `redirectRoute('purchases.invoice_preview', ['purchase' => $purchase], navigate: true)`.

## D. Purchases list and invoice preview

### List (`livewire.purchase.index`)

- `WithPagination`; `#[Url] public string $search = ''` bound with `wire:model.live.debounce.300ms="search"`, and `#[Url] public string $warehouse = ''` bound with `wire:model.live="warehouse"` (matching the sales index).
- Query: `Purchase::query()->with(['party', 'warehouse'])`; search matches `document_number` or supplier `name`; warehouse filter matches `warehouse_id`; `latest('invoice_date')->latest('id')->paginate(10)`.
- Columns: document number (link to `purchases.invoice_preview`), invoice date, supplier, warehouse, total, paid, remaining, status badge.
- Empty state uses `purchases.no_records`.
- A “New Purchase” button links to `purchases.create`.

### Invoice preview

- `PurchaseInvoicePreviewController::__invoke(Purchase $purchase): View` mirrors `SaleInvoicePreviewController`: load `items`, `party`, `creator`, and `payments` filtered to `PaymentType::payment` oldest-first; read `Settings::all()`; pass `purchase`, `store` (name/address/phone), `invoiceFooter`, and `paymentMethod` to the view.
- `resources/views/purchases/invoice-preview.blade.php` mirrors `sales/invoice-preview.blade.php` with purchase copy: “Purchase Invoice”, supplier and warehouse, item lines, subtotal/discount/tax/total, payment summary, and the existing Print control.
- Missing ids return 404 through normal route-model binding.

## E. Navigation, language, and party history link

- Sidebar: replace the `Purchases` item's `'#'`/`navigate => false` with `route('purchases.index')`, `request()->routeIs('purchases.*')`, and `wire:navigate`; keep the existing icon path. `nav_purchases` already exists in `lang/en/dashboard.php`.
- `lang/en/purchases.php`: title, subtitle, list title/subtitle, form labels, field labels, buttons, flash/labels for status, supplier, warehouse, dates, item table, totals, payment details, invoice labels, `no_records`, `no_products`, `paid_exceeds_total`, and invoice footer copy.
- Party show page (`resources/views/components/livewire/party/⚡show.blade.php`): add a nullable `purchase_id` to the transaction objects, set it for purchase rows, and link those document numbers to `purchases.invoice_preview`.

## F. Tests

### `tests/Feature/PurchasesCreateTest.php`

1. Guest is redirected from `purchases.create`.
2. Form renders with today's invoice date, +14-day due date, sole active warehouse preselected, sole active cashbox preselected, and only supplier/both parties in the supplier options.
3. Empty required payload produces supplier/warehouse/item validation errors and creates no purchase.
4. Paid amount above total produces `paid_exceeds_total` and creates no purchase.
5. Draft save persists `status = draft`, items, and totals, but creates no `StockMovement` and no `Payment`.
6. Non-draft save creates purchase, items, one `IN` stock movement per physical product with the line's unit price as `unit_cost`, and a supplier payment with `purchase_id` and `type = payment`.
7. Zero paid amount yields `pending`; partial paid yields `partial`; full paid yields `paid`.
8. Service product lines are saved but create no stock movement.
9. Save redirects to `purchases.invoice_preview`.
10. Document numbers use the configured `purchase` and `payment` sequences.

### `tests/Feature/PurchasesIndexTest.php`

11. Guest is redirected from `purchases.index`.
12. Index lists supplier, warehouse, totals, status, and document-number links to the preview.
13. Search matches document number and supplier name; no match shows `purchases.no_records`.
14. Warehouse filter shows only that warehouse's purchases.
15. Index paginates at 10 and orders newest first.

### `tests/Feature/PurchaseInvoicePreviewTest.php`

16. Preview renders purchase/supplier/warehouse details, item lines, totals, and payment method; guest is redirected.
17. Missing purchase id returns 404.
18. Party show page links a purchase document number to the preview.

## G. Verification

- `vendor/bin/pest tests/Feature/PurchasesCreateTest.php tests/Feature/PurchasesIndexTest.php tests/Feature/PurchaseInvoicePreviewTest.php --compact`
- `vendor/bin/pest --compact`
- `vendor/bin/pint --dirty --format agent`
- `pnpm run build`
- `vendor/bin/phpstan analyse --no-progress`

The feature is complete only when all checks are green and the full suite shows no regressions.
