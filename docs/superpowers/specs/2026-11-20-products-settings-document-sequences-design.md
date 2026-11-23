# Products, Settings & Document Sequences — Design

- **Date:** 2026-11-20
- **Status:** Approved design, pending spec review
- **Scope owner:** MiniPOS admin area

## Objective

Give the shop admin three missing back-office capabilities:

1. A product catalog with pricing, classification, and current stock visibility.
2. A fixed General settings form (store identity, currency, tax rate, invoice footer) backed by the existing `settings` table.
3. Document-sequence management plus a reusable, reset-aware document number generator that sales, purchases, payments, returns, and expenses can call.

The sales and purchases entry screens are not implemented in this plan; the numbering service ships ready for them.

## Non-goals

- Sale, purchase, payment, return, or expense entry flows.
- Opening-stock entry, stock adjustments, stock transfers, or warehouse management.
- A generic settings key/value editor.
- Creating or deleting document sequence rows (types are application-defined).
- Caching settings or document sequences.
- Wiring document numbers into the sales create screen (currently sample data).

## Conventions

- Livewire 4 single-file components under `resources/views/components/livewire/`, named via `Route::livewire()`.
- Follow the catalog pattern: index page plus `actions/create|update|delete` child components, `#[On]` refresh listeners, Alpine flash banner, confirmation modal, soft deletes.
- Shared form components `x-form.input`, `x-form.select`, `x-form.textarea`, `x-form.checkbox`.
- Pest feature tests; run the relevant test file after every change, full suite at the end; `vendor/bin/pint` on changed PHP files.
- English lang files per feature under `lang/en/`.

---

## A. Products

### A1. Schema and model

Edit the existing migration `2026_11_06_161737_create_products_table.php` (no new migration):

```php
$table->string('type', 20)->default('product')->after('name');
```

`App\Enums\ProductType` becomes string-backed:

```php
enum ProductType: string
{
    case Product = 'product';
    case Service = 'service';
}
```

- `Product`: add `'type'` to `$fillable`; cast `'type' => ProductType::class`.
- `App\Models\Unit`: add the `HasFactory` trait (its factory exists but is not wired; product tests need `Unit::factory()`).
- `ProductFactory`: add a `service()` state that sets `type` to `ProductType::Service`.
- Add `lang/en/ProductType.php` with `Product` and `Service` labels, matching the enum's existing `__('ProductType.Product')` calls.
- **Live database:** the development database is SQLite (`database/database.sqlite`) and the `products` table already exists. Add the column through the schema builder (`Schema::table('products', fn ($table) => $table->string('type', 20)->default('product'))`), or rebuild with `php artisan migrate:fresh --seed` (destructive). The plan uses the schema-builder route for the dev database.

### A2. Index page

- Route: `Route::livewire('/products', 'livewire.catalog.products.index')->name('products.index');` inside the auth group.
- Sidebar: point the existing Products item at `route('products.index')`, `routeIs('products.*')`, `wire:navigate`.
- Component: `resources/views/components/livewire/catalog/products/⚡index.blade.php`.
- Search (`#[Url]`, debounced) across `name`, `code`, `sku`, `barcode`.
- Pagination: 10 per page, newest first.
- Columns: `#`, code, name, category, brand, unit, type, selling price, stock, active, actions.
- Eager-load `category`, `brand`, `unit` to avoid N+1.
- Stock query:

```php
->withSum(['stockMovements as stock_in' => fn ($q) => $q->where('movement_type', MovementType::IN)], 'quantity')
->withSum(['stockMovements as stock_out' => fn ($q) => $q->where('movement_type', MovementType::OUT)], 'quantity')
```

- Displayed stock = `stock_in - stock_out`. Treat a null sum (no movements) as `0`. Services show `—` instead of a quantity.
- Low-stock badge when `type === Product` and `stock_in - stock_out <= min_stock`.
- Empty search result shows `products.no_records`.

### A3. Create and update

Child components under `.../catalog/products/actions/`.

| Field | Rules |
| --- | --- |
| `code` | required, string, max 20, unique `products.code` (ignore own id on update) |
| `type` | required, in `ProductType` values; default `product` |
| `sku` | nullable, string, max 50, unique `products.sku` (ignore own id on update) |
| `barcode` | nullable, string, max 50, unique `products.barcode` (ignore own id on update) |
| `name` | required, string, max 255 |
| `description` | nullable, string |
| `categoryId` | nullable, integer, exists `categories.id` |
| `brandId` | nullable, integer, exists `brands.id` |
| `unitId` | nullable, integer, exists `units.id` |
| `purchasePrice` | required, numeric, min 0 |
| `sellingPrice` | required, numeric, min 0 |
| `minStock` | required, numeric, min 0 |
| `isActive` | boolean |

- Nullable relation selects bind as `int|string` with `''` for "none" and are stored as `null`.
- Empty SKU/barcode are stored as `null` so the unique indexes allow multiple blanks.
- Create and update dispatch `product-created` / `product-updated`.
- Update pre-fills all fields including relations and type.

### A4. Delete

- Soft delete after confirmation modal, dispatch `product-deleted`.
- No deletion guard: stock movements and document lines keep their foreign keys; the product is only hidden.

### A5. Tests (`tests/Feature/ProductPagesTest.php`)

1. Route exists and guests are redirected.
2. Index lists products with category/brand/unit and type label.
3. Search matches name, code, SKU, and barcode (dataset).
4. No-match search shows the empty state.
5. List refreshes on `product-updated`.
6. Create persists all fields, casts type, and dispatches `product-created`.
7. Create validation rejects duplicate code/SKU/barcode and missing required fields.
8. A service product can be created without stock data.
9. Update persists changes, ignores its own unique values, and rejects another product's code.
10. Delete soft-deletes and dispatches `product-deleted`.

---

## B. Settings

### B1. Access layer

New `App\Support\Settings` (new `app/Support` folder):

```php
Settings::get(string $key, mixed $default = null): mixed
Settings::set(string $key, mixed $value, string $type = 'string', ?string $group = 'general'): void
Settings::all(?string $group = null): array // key => typed value
```

Casting rules, driven by `settings.type`:

| Type | Read | Write |
| --- | --- | --- |
| `string` | `(string) value` | `(string) value` |
| `integer` | `(int) value` | `(string) value` |
| `boolean` | `filter_var(value, FILTER_VALIDATE_BOOL)` | `'1'` / `'0'` |
| `decimal` | `(string) value` (precision preserved; callers cast) | `(string) value` |
| `json` | `json_decode(value, true)` | `json_encode(value)` |

- Unknown stored types read as strings.
- `set()` uses `updateOrCreate(['key' => ...], ['value' => ..., 'type' => ..., 'group' => ...])`.
- No cache. Settings are read on demand.
- `App\Models\Setting`: add `HasFactory`.

### B2. Fields and page

Fixed General form, route `/settings`, name `settings.index`, component `livewire.settings.index`.

| Key | Label | Type | Default |
| --- | --- | --- | --- |
| `company_name` | Company name | string | `MiniPOS Store` |
| `company_phone` | Phone | string | `''` |
| `company_address` | Address | string | `''` |
| `currency` | Currency code | string | `USD` |
| `tax_rate` | Tax rate (%) | decimal | `0` |
| `invoice_footer` | Invoice footer | string | `''` |

- Page loads values via `Settings::get()` with the defaults above, so a missing row never breaks the page.
- Save validates:

| Field | Rules |
| --- | --- |
| `companyName` | required, string, max 255 |
| `companyPhone` | nullable, string, max 30 |
| `companyAddress` | nullable, string, max 500 |
| `currency` | required, string, max 3 |
| `taxRate` | required, numeric, min 0, max 100 |
| `invoiceFooter` | nullable, string, max 1000 |

- Save writes each key with its declared type/group and shows a success banner (`saved` flag + Alpine timeout), matching the catalog flash pattern.
- Sidebar gains a Settings item with a gear icon.
- Extend the settings block in `TestSeeder` with `company_phone`, `company_address`, and `invoice_footer` (the existing three keys remain).

### B3. Tests (`tests/Feature/SettingsTest.php`)

1. `Settings::get()` returns the fallback for a missing key.
2. `Settings::set()`/`get()` round-trips string, integer, boolean, decimal, and JSON values.
3. `Settings::all('general')` returns only that group, typed.
4. Settings page loads seeded values and defaults.
5. Saving persists every field and casts types.
6. Validation rejects an empty company name and an out-of-range tax rate.
7. Guests are redirected.

---

## C. Document sequences

### C1. Fixes before building

- `ResetPeriod` is missing the `NEVER = 'never'` case even though the column default is `never`; casting such a row currently throws. Add the case and its label.
- Add `lang/en/ResetPeriod.php` (`Daily`, `Weekly`, `Monthly`, `Yearly`, `Never`) because the enum already resolves those keys.
- `App\Models\DocumentSequence`: add `HasFactory`.

### C2. Generator service

New `App\Support\DocumentNumber`:

```php
DocumentNumber::next(string $documentType): string
```

Algorithm:

1. Run inside `DB::transaction()`.
2. Select the sequence for the type with `lockForUpdate()`.
3. Throw `RuntimeException("No document sequence configured for [type].")` when absent — configuration errors stay visible.
4. Reset when the stored `last_reset_at` falls outside the current period (app timezone); otherwise leave the number alone. Stamp `last_reset_at = now()` on reset and on first use when it is null.
5. Format `prefix` + `str_pad(next_number, padding, '0', STR_PAD_LEFT)`.
6. Increment `next_number` inside the same locked transaction and return the formatted number.

Reset rules:

| `reset_period` | Reset when |
| --- | --- |
| `never` | Never |
| `daily` | `last_reset_at` is null or not the same calendar day |
| `weekly` | null or not the same ISO week |
| `monthly` | null or a different month/year |
| `yearly` | null or a different year |

- The row's `padding` is respected (no hardcoded six digits).
- `lockForUpdate` is a no-op on SQLite tests but enforces serialization on Postgres.

### C3. Management page

- Route `/document-sequences`, name `document-sequences.index`, component `livewire.document-sequences.index`.
- Lists: type (headline-cased), prefix, next number, formatted preview, padding, reset period, last reset, edit action.
- Edit modal child component `actions/edit`:
  - `prefix`: required, string, max 20
  - `nextNumber`: required, integer, min 1
  - `padding`: required, integer, min 1, max 10
  - `resetPeriod`: required, in `ResetPeriod` values
  - Dispatch `document-sequence-updated`; index refreshes and flashes.
- Preview uses the same formatting as the service.
- No create/delete. Lowering `next_number` may produce duplicates; the edit form carries a help note but no hard guard.
- Sidebar gains a Document Sequences item.

### C4. Integration and cleanup

- Replace the private `nextDocumentNumber()` in `DemoDataSeeder` with `DocumentNumber::next()`.
- Add `document_number` to `Sale::$fillable` (currently omitted, so sales cannot set their number).
- Purchases already include `document_number`; no change there.

### C5. Tests (`tests/Feature/DocumentSequenceTest.php`)

1. Formats with the row's padding and increments.
2. Consecutive calls return consecutive numbers.
3. Resets daily, weekly, monthly, and yearly at the expected boundary (`travelTo`).
4. Does not reset inside the period.
5. `never` never resets.
6. Unknown document type throws.
7. Management page lists sequences and shows the preview.
8. Edit persists and dispatches the event.
9. Edit validation rejects padding outside 1–10, an empty prefix, and an invalid reset period.
10. Guests are redirected.

---

## Rollout and risks

- **Products migration:** edited in place. On the SQLite development database add the column with `Schema::table(...)` or rebuild with `migrate:fresh --seed` (which destroys data). Tests use `RefreshDatabase` on SQLite and pick up the edited migration automatically.
- **ProductType conversion:** pure enum → string-backed. Safe now because nothing references it; grep confirms no usage.
- **Sequences during seeding:** `DemoDataSeeder` requires `TestSeeder`, which creates all six sequences before numbers are requested, so the service will not throw there. Monthly reset with a null `last_reset_at` will not reset mid-seed.
- **Concurrency:** sequence increments are serialized on databases that honour row locks (Postgres) via `lockForUpdate`; SQLite treats it as a no-op, so concurrent numbering is not stress-tested in the dev environment.
- **Timezone:** reset boundaries use the application timezone.
- **Settings concurrency:** last write wins; acceptable for a single-admin back office.

## Acceptance

- Products page supports full CRUD, classification, type, pricing, and stock visibility, with soft delete.
- Settings page edits the six general values, and the typed service returns correct PHP types.
- Document sequences are editable from the UI, and `DocumentNumber::next()` produces `PREFIX-000001`-style numbers, incrementing and resetting correctly.
- All new and existing tests pass; Pint is clean.
