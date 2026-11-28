# Warehouse Management & Sales Link — Design

- **Date:** 2026-11-25
- **Status:** Approved design, pending spec review
- **Scope owner:** MiniPOS admin area

## Objective

Give the shop admin a usable Warehouse area and connect it to sales history:

1. Create, edit, search, and deactivate warehouses from a dedicated index page.
2. Open a warehouse to see the paginated sales recorded against it, with links to the existing invoice previews.
3. Keep historical sales intact when a warehouse is deactivated.

## Non-goals

- Stock transfers, opening-stock entry, or per-warehouse stock enforcement during sales.
- Soft deletion or hard deletion of warehouses.
- Changes to the New Sale form beyond the existing active-warehouse dropdown.
- Changes to reports, purchases, expenses, or inventory screens.
- A general sales-list page independent of a warehouse.
- New migrations, seeders, or schema changes.

## Conventions

- Livewire 4 single-file components under `resources/views/components/livewire/`, registered with `Route::livewire()`.
- Follow the catalog pattern: index page plus `actions/create` and `actions/update` modal components, `#[On]` refresh listeners, Alpine flash banner, and shared form/table components.
- Prepare queries in component computed properties or methods rather than Blade, and add `wire:key` to repeated table rows.
- Bind the search input with `wire:model.live.debounce.300ms="search"`.
- Pest feature tests using `User::factory()`, `Warehouse::factory()`, `Party::factory()`, and `Sale::factory()`; run the focused test file after each change and the full suite at the end; run Pint on changed PHP files and PHPStan before completion.
- English lang files under `lang/en/`.
- Prefer named routes and `route()` for all links.

---

## A. Routes and component layout

Add these routes inside the existing `auth` group in `routes/web.php`:

```php
Route::livewire('/warehouses', 'livewire.warehouses.index')->name('warehouses.index');
Route::livewire('/warehouses/{warehouse}', 'livewire.warehouses.show')
    ->whereNumber('warehouse')
    ->name('warehouses.show');
```

New components:

- `resources/views/components/livewire/warehouses/⚡index.blade.php`
- `resources/views/components/livewire/warehouses/actions/⚡create.blade.php`
- `resources/views/components/livewire/warehouses/actions/⚡update.blade.php`
- `resources/views/components/livewire/warehouses/⚡show.blade.php`

The existing empty `app/Http/Controllers/WarehouseController.php` scaffold stays untouched; no controller changes are needed.

The existing `Warehouse::sales()` relation and `sales.warehouse_id` column are the only data link. No model or migration changes are required.

## B. Warehouse index

- Component uses `WithPagination` and a `#[Url]` `search` property bound with `wire:model.live.debounce.300ms="search"`.
- Query: `Warehouse::query()->withCount('sales')->when($search, ...)->latest('id')->paginate(10)`.
- Search matches `code` and `name`.
- Table columns: `#`, code, name, address, active badge, sales count, created date, actions.
- Empty result uses `warehouses.no_records`.
- Actions per row:
  - **Edit** opens the `actions.update` modal.
  - **Deactivate / Activate** is an inline `wire:click="toggleActive({{ $warehouse->id }})"` button; it flips only `is_active`, dispatches `warehouse-updated`, and has no confirmation modal because it is reversible.
  - **View sales** links to `route('warehouses.show', $warehouse)`.

## C. Create and update

Child components follow the brands/categories modal pattern.

| Field | Rules |
| --- | --- |
| `code` | required, string, max 20, unique `warehouses.code` (ignore own id on update) |
| `name` | required, string, max 255 |
| `address` | nullable, string |
| `note` | nullable, string |
| `isActive` | boolean |

- Create dispatches `warehouse-created`; update dispatches `warehouse-updated`.
- Update pre-fills all fields, including the active checkbox.
- Both modals close on their own event, as existing action components do.
- Validation errors render through the existing `x-form.input` / `x-form.textarea` error display.

## D. Warehouse show page

- Component receives `public Warehouse $warehouse` by route-model binding. Unknown ids produce a normal 404.
- The page remains available for inactive warehouses.
- Header area shows the warehouse name, code, active badge, address, and note, with a breadcrumb link back to `warehouses.index`.
- Summary and table data come from `#[Computed]` methods so Blade does not issue queries:
  - `$this->salesCount`: `$this->warehouse->sales()->count()`
  - `$this->salesTotal`: `$this->warehouse->sales()->sum('total_amount')`, displayed with `number_format(..., 2)`
  - `$this->sales`: the paginator below
- Sales table uses `WithPagination`, 10 per page:

```php
#[Computed]
public function sales()
{
    return $this->warehouse->sales()
        ->with('party')
        ->latest('invoice_date')
        ->latest('id')
        ->paginate(10);
}
```

- Columns: document number, invoice date, customer, total, status, and an invoice link.
- Customer is `$sale->party?->name ?? __('sales.no_customer')`.
- Status renders `$sale->status->label()` from the existing `DocumentStatus` enum.
- Invoice link uses `route('sales.invoice_preview', $sale)`.
- Empty state uses `warehouses.no_sales`.

## E. Navigation and language

- Add `lang/en/warehouses.php` with title, subtitle, field labels, status labels, flash messages, `no_records`, and `no_sales`.
- Add `nav_warehouses` to `lang/en/dashboard.php`.
- Add a sidebar item after Products (before Sales) using a building icon path consistent with the existing Heroicons outline paths, `route('warehouses.index')`, `request()->routeIs('warehouses.*')`, and `wire:navigate`. No new icon component is needed.
- Do not change existing sales language keys.

## F. Interaction with sales

- The New Sale component already builds its warehouse dropdown from `Warehouse::query()->where('is_active', true)->orderBy('name')`.
- Deactivated warehouses therefore disappear from new sales immediately, while all old sales keep their `warehouse_id` and remain visible on the warehouse show page.
- No changes to sale validation, save logic, stock movements, or invoice preview.

## G. Tests (`tests/Feature/WarehousePagesTest.php`)

1. Guests are redirected from `warehouses.index` and `warehouses.show`.
2. Index lists warehouses with code, name, address, active status, and sales count.
3. Search filters by code and name; no-match shows `warehouses.no_records`.
4. Index paginates at 10 records.
5. Index refreshes when `warehouse-created` or `warehouse-updated` fires.
6. Create persists all fields and dispatches `warehouse-created`.
7. Create rejects a duplicate code with a unique validation error and dispatches no event.
8. Create rejects an empty required-field payload with required errors and dispatches no event.
9. Update persists changes and dispatches `warehouse-updated`.
10. Updating with its own code passes unique validation; another warehouse's code is rejected.
11. Deactivation sets `is_active` to false, dispatches `warehouse-updated`, and hides the warehouse from `livewire.sales.create`'s `warehouseOptions`.
12. Activation sets `is_active` to true and restores the warehouse in `warehouseOptions`.
13. Show renders warehouse details and sales summary, and returns 404 for a missing id.
14. Show lists only that warehouse's sales with customer, status, total, and `sales.invoice_preview` URL.
15. Show paginates sales at 10 and excludes another warehouse's sale.
16. Show still works for an inactive warehouse and displays its historical sales.
17. Index escapes warehouse names, addresses, and notes rendered from user input.

## H. Verification

- `vendor/bin/pest --compact tests/Feature/WarehousePagesTest.php`
- `vendor/bin/pest --compact`
- `vendor/bin/pint --dirty --format agent`
- `vendor/bin/phpstan analyse --no-progress`

Commit the feature only after all checks are green and code review has no unresolved Critical or Important findings.
