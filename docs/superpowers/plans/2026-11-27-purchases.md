# Purchases Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Purchases area with a draft-capable New Purchase form, a filtered list, and a printable purchase invoice preview.

**Architecture:** Three authenticated Livewire 4 SFCs use the existing `Purchase`, `PurchaseItem`, `StockMovement`, and `Payment` models. A new migration widens `purchases.status` to allow `draft`. The create form mirrors `livewire.sales.create` but requires a supplier, defaults prices from `purchase_price`, creates `IN` stock movements for physical products, and optionally records a supplier payment; the saved document redirects to a print controller that mirrors the sale preview.

**Tech Stack:** Laravel 13.40.0, PHP 8.5, Livewire 4.4.8 SFCs (`⚡` prefix), Pest 5.3.5, Blade components (`x-page-header`, `x-card`, `x-table`, `x-form.*`, `x-badge`, `x-button`, `x-modal`), Tailwind CSS v4, SQLite.

**Spec:** `docs/superpowers/specs/2026-11-27-purchases-design.md`

## Global Constraints

- Routes go inside the existing `['middleware' => ['auth']]` group in `routes/web.php`; use `Route::livewire()` for pages and `Route::get()` for the print view.
- Do not change `livewire.sales.create`, `livewire.sales.index`, the sale invoice preview, or any seeder. The empty `PurchaseController` stays untouched.
- Supplier is required and limited to active parties of type `supplier` or `both`.
- Warehouse and cashbox options come from active records only; preselect the sole option in `mount()`, otherwise show a blank prompt.
- `paidAmount` is ignored and stored as 0 for drafts. Status: draft → `DRAFT`; otherwise paid >= total → `PAID`, paid > 0 → `PARTIAL`, else `PENDING`.
- Physical product lines create one `StockMovement` with `movement_type = IN`, `unit_cost = unit_price`, `reference_type = Purchase::class`; service lines create none.
- All numbers come from `DocumentNumber::next('purchase')` and `DocumentNumber::next('payment')`; writes happen inside `DB::transaction()`.
- Payments use `type = payment`, `purchase_id`, and the supplier's `party_id`.
- Lists paginate 10 records; search uses `wire:model.live.debounce.300ms`, the warehouse filter uses `wire:model.live`.
- All copy goes in `lang/en/purchases.php`; `nav_purchases` already exists in `lang/en/dashboard.php`. Use `DocumentStatus`/`PaymentType` enum labels from existing lang files.
- Prefer named routes and `route()` for links; add `wire:key` to repeated rows.
- TDD: write failing Pest tests first, run them, implement, rerun. Run `vendor/bin/pint --dirty --format agent` after PHP changes and `pnpm run build` after Blade changes.
- Verification commands: `vendor/bin/pest tests/Feature/PurchasesCreateTest.php tests/Feature/PurchasesIndexTest.php tests/Feature/PurchaseInvoicePreviewTest.php --compact`, `vendor/bin/pest --compact`, `vendor/bin/phpstan analyse --no-progress`.
- Commit each task after its verification. If the user prefers to keep work uncommitted, skip the commit step and continue.

## Review Focus

- A service product on a purchase must be saved as a line without creating a stock movement — pinned by the service-line test in Task 3.
- A draft with a non-zero paid amount entered must store `paid_amount = 0` and create no payment or stock movement — pinned by the draft test in Task 3.
- A paid amount above the grand total must be rejected without creating any records — pinned by the overpayment test in Task 3.
- The status migration must accept `draft` on SQLite without losing existing `pending`/`paid` values — pinned by the migration tests in Task 1.
- Supplier names are user input rendered in the list and must be escaped — pinned by the escaping test in Task 4; purchase links in party history must point to the preview — pinned by Task 5.

---

## File Structure

- Create `database/migrations/2026_11_27_*_add_draft_to_purchases_status.php`: widen the status enum.
- Create `app/Http/Controllers/PurchaseInvoicePreviewController.php`: print-only controller.
- Create `resources/views/purchases/invoice-preview.blade.php`: printable purchase document.
- Create `resources/views/components/livewire/purchase/⚡create.blade.php`: New Purchase form and save logic.
- Create `resources/views/components/livewire/purchase/⚡index.blade.php`: searchable, warehouse-filtered list.
- Create `lang/en/purchases.php`: all purchases copy.
- Create `lang/en/PaymentMethod.php`: payment-method enum labels used by the preview and form.
- Create `tests/Feature/PurchasesCreateTest.php`, `tests/Feature/PurchasesIndexTest.php`, `tests/Feature/PurchaseInvoicePreviewTest.php`.
- Modify `routes/web.php`, `resources/views/components/layout/sidebar.blade.php`, `resources/views/components/livewire/party/⚡show.blade.php`.

---

### Task 1: Draft status migration

**Files:**
- Create: `database/migrations/2026_11_27_000000_add_draft_to_purchases_status.php`
- Test: `tests/Feature/PurchasesCreateTest.php`

**Interfaces:**
- Consumes: `Purchase::factory()`, `App\Enums\DocumentStatus`.
- Produces: `purchases.status` accepts `draft`, `pending`, `paid`, `partial`; later tasks rely on `DocumentStatus::DRAFT` persisting.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/PurchasesCreateTest.php` with `uses(RefreshDatabase::class)` and:

```php
test('a purchase can be saved with draft status', function () {
    $purchase = Purchase::factory()->create(['status' => DocumentStatus::DRAFT]);

    expect($purchase->fresh()->status)->toBe(DocumentStatus::DRAFT);
});

test('existing purchase statuses remain valid after the migration', function () {
    foreach ([DocumentStatus::PENDING, DocumentStatus::PARTIAL, DocumentStatus::PAID] as $status) {
        expect(Purchase::factory()->create(['status' => $status])->fresh()->status)->toBe($status);
    }
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/PurchasesCreateTest.php --compact`
Expected: FAIL — SQLite check constraint rejects `draft`.

- [ ] **Step 3: Add the migration**

In `up()`, use `Schema::table('purchases', ...)` with `$table->enum('status', ['draft', 'pending', 'paid', 'partial'])->default('pending')->change();`. In `down()`, restore `['pending', 'paid', 'partial']` the same way.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/PurchasesCreateTest.php --compact`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit (skip if the user asked to keep changes uncommitted)**

`git add database/migrations/... tests/Feature/PurchasesCreateTest.php && git commit -m "feat(purchases): allow draft status"`

---

### Task 2: Purchase invoice preview

**Files:**
- Create: `app/Http/Controllers/PurchaseInvoicePreviewController.php`
- Create: `resources/views/purchases/invoice-preview.blade.php`
- Create: `tests/Feature/PurchaseInvoicePreviewTest.php`
- Modify: `routes/web.php`, `lang/en/purchases.php`
- Create: `lang/en/PaymentMethod.php`

**Interfaces:**
- Consumes: `Purchase`, `App\Support\Settings`, `App\Enums\PaymentType`, `App\Enums\PaymentMethod`, shared sale preview layout conventions.
- Produces: named route `purchases.invoice_preview` at `/purchases/{purchase}/invoice-preview` with `whereNumber('purchase')`; view variables `$purchase`, `$store`, `$invoiceFooter`, `$paymentMethod`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/PurchaseInvoicePreviewTest.php` with `uses(RefreshDatabase::class)`:

```php
test('purchase invoice preview is auth protected', function () {
    $purchase = Purchase::factory()->create();

    expect(route('purchases.invoice_preview', $purchase))
        ->toBe(url('/purchases/'.$purchase->id.'/invoice-preview'));

    $this->get(route('purchases.invoice_preview', $purchase))->assertRedirect();
});

test('purchase invoice preview renders document details and payment', function () {
    $user = User::factory()->create();
    $supplier = Party::factory()->supplier()->create(['name' => 'Acme Supplier']);
    $warehouse = Warehouse::factory()->create(['name' => 'Main Store']);
    $purchase = Purchase::factory()->for($supplier, 'party')->for($warehouse)->create([
        'document_number' => 'PUR-PREVIEW',
        'total_amount' => 150,
        'paid_amount' => 50,
    ]);
    Payment::factory()->for($purchase, 'purchase')->create([
        'party_id' => $supplier->id,
        'type' => PaymentType::payment,
        'amount' => 50,
        'method' => PaymentMethod::BANK_TRANSFER,
    ]);

    $this->actingAs($user)->get(route('purchases.invoice_preview', $purchase))
        ->assertOk()
        ->assertSee('PUR-PREVIEW')
        ->assertSee('Acme Supplier')
        ->assertSee('Main Store')
        ->assertSee('150.00')
        ->assertSee(PaymentMethod::BANK_TRANSFER->label());
});

test('purchase invoice preview returns 404 for a missing purchase', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('purchases.invoice_preview', 999999))
        ->assertNotFound();
});
```

Also add to `lang/en/purchases.php`: `invoice_title`, `supplier`, `warehouse`, `invoice_date`, `due_date`, `item`, `quantity`, `unit_price`, `discount`, `tax`, `line_total`, `subtotal`, `grand_total`, `paid_amount`, `due_amount`, `payment_method`, `bill_from`, `invoice_number`, `thank_you`, `print`.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/PurchaseInvoicePreviewTest.php --compact`
Expected: FAIL — route not defined.

- [ ] **Step 3: Implement the controller, route, and view**

`PurchaseInvoicePreviewController::__invoke(Purchase $purchase): View`: load `items`, `party`, `creator`, and `payments` filtered to `PaymentType::payment` oldest-first; call `Settings::all()`; return `view('purchases.invoice-preview', ...)` with the four variables above. Mirror `sales/invoice-preview.blade.php` markup, substituting purchase labels and using `$purchase->party?->name` as supplier. Add `lang/en/PaymentMethod.php` with keys `Cash`, `BankTransfer`, `CreditCard`, `Wallet` (the exact keys `PaymentMethod::label()` looks up). Add the route from the Interfaces block.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/PurchaseInvoicePreviewTest.php --compact`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit (skip if the user asked to keep changes uncommitted)**

`git add app/Http/Controllers/PurchaseInvoicePreviewController.php resources/views/purchases/invoice-preview.blade.php routes/web.php lang/en/purchases.php lang/en/PaymentMethod.php tests/Feature/PurchaseInvoicePreviewTest.php && git commit -m "feat(purchases): add printable invoice preview"`

---

### Task 3: New Purchase form

**Files:**
- Create: `resources/views/components/livewire/purchase/⚡create.blade.php`
- Modify: `routes/web.php`, `lang/en/purchases.php`, `tests/Feature/PurchasesCreateTest.php`

**Interfaces:**
- Consumes: `ProductMatcher::search()`, `DocumentNumber::next()`, `StockMovement`, `Payment`, `Auth::id()`, computed-property conventions from `livewire.sales.create`.
- Produces: full-page component `livewire.purchase.create` at named route `purchases.create`. Public properties: `partyId`, `warehouseId`, `invoiceDate`, `dueDate`, `notes`, `isDraft`, `paymentMethod`, `cashboxId`, `paidAmount`, `items`, `searchTerm`, `searchResults`. Public methods: `mount()`, `addItem()`, `removeItem(int $index)`, `updatedSearchTerm()`, `addFirstSearchResult(string $term)`, `addProductFromSearch(int $productId)`, `save()`, `supplierOptions()`, `warehouseOptions()`, `cashboxOptions()`, `paymentMethodOptions()`. Computed: `subtotal()`, `discountTotal()`, `taxTotal()`, `grandTotal()`, `dueAmount()`. Private helpers: `rules()`, `deriveStatus(float $paid, float $total): DocumentStatus`, `buildPurchaseItems($products): array`, `lineBase(array $item): float`, `paidFloat(): float`, `collectItems(callable $callback): float`, `lineSubtotalSum(): float`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/PurchasesCreateTest.php`:

```php
test('new purchase form defaults dates and preselects the sole warehouse and cashbox', function () {
    $user = User::factory()->create();
    Party::factory()->supplier()->create(['name' => 'Acme Supplier']);
    Party::factory()->customer()->create(['name' => 'Walk-in Customer']);
    $warehouse = Warehouse::factory()->create();
    $cashbox = Cashbox::factory()->create();

    $component = Livewire::actingAs($user)->test('livewire.purchase.create');

    expect($component->instance()->invoiceDate)->toBe(today()->format('Y-m-d'))
        ->and($component->instance()->dueDate)->toBe(today()->addDays(14)->format('Y-m-d'))
        ->and($component->instance()->warehouseId)->toBe($warehouse->id)
        ->and($component->instance()->cashboxId)->toBe($cashbox->id);

    $component->assertSee('Acme Supplier')->assertDontSee('Walk-in Customer');
});

test('empty new purchase shows validation errors and saves nothing', function () {
    Livewire::actingAs(User::factory()->create())->test('livewire.purchase.create')
        ->set('partyId', '')
        ->set('warehouseId', '')
        ->set('items.0.product_id', '')
        ->call('save')
        ->assertHasErrors(['partyId' => 'required', 'warehouseId' => 'required', 'items.0.product_id' => 'required']);

    expect(Purchase::query()->count())->toBe(0);
});

test('paid amount above grand total is rejected', function () {
    $product = Product::factory()->create(['purchase_price' => 100]);
    Livewire::actingAs(User::factory()->create())->test('livewire.purchase.create')
        ->set('partyId', Party::factory()->supplier()->create()->id)
        ->set('warehouseId', Warehouse::factory()->create()->id)
        ->set('cashboxId', Cashbox::factory()->create()->id)
        ->set('items.0.product_id', $product->id)
        ->set('items.0.quantity', 1)
        ->set('items.0.unit_price', 100)
        ->set('paidAmount', 101)
        ->call('save')
        ->assertHasErrors('paidAmount');

    expect(Purchase::query()->count())->toBe(0);
});

test('draft purchase ignores paid amount and creates no stock or payment', function () {
    $user = User::factory()->create();
    $supplier = Party::factory()->supplier()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create(['purchase_price' => 40]);
    Cashbox::factory()->create();

    Livewire::actingAs($user)->test('livewire.purchase.create')
        ->set('partyId', $supplier->id)
        ->set('warehouseId', $warehouse->id)
        ->set('items.0.product_id', $product->id)
        ->set('items.0.quantity', 2)
        ->set('items.0.unit_price', 40)
        ->set('paidAmount', 80)
        ->set('isDraft', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('purchases.invoice_preview', Purchase::query()->firstOrFail()));

    $purchase = Purchase::query()->firstOrFail();

    expect($purchase->status)->toBe(DocumentStatus::DRAFT)
        ->and((float) $purchase->paid_amount)->toBe(0.0)
        ->and($purchase->items)->toHaveCount(1)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0);
});

test('non draft purchase records stock received and supplier payment', function () {
    $user = User::factory()->create();
    $supplier = Party::factory()->supplier()->create();
    $warehouse = Warehouse::factory()->create();
    $cashbox = Cashbox::factory()->create();
    $product = Product::factory()->create(['purchase_price' => 25]);

    Livewire::actingAs($user)->test('livewire.purchase.create')
        ->set('partyId', $supplier->id)
        ->set('warehouseId', $warehouse->id)
        ->set('items.0.product_id', $product->id)
        ->set('items.0.quantity', 3)
        ->set('items.0.unit_price', 25)
        ->set('paidAmount', 30)
        ->call('save')
        ->assertHasNoErrors();

    $purchase = Purchase::query()->firstOrFail();
    $movement = StockMovement::query()->firstOrFail();
    $payment = Payment::query()->firstOrFail();

    expect($purchase->status)->toBe(DocumentStatus::PARTIAL)
        ->and($purchase->document_number)->toStartWith('PUR-')
        ->and((float) $purchase->total_amount)->toBe(75.0)
        ->and($movement->movement_type)->toBe(MovementType::IN)
        ->and((float) $movement->unit_cost)->toBe(25.0)
        ->and($movement->reference_type)->toBe(Purchase::class)
        ->and($movement->reference_id)->toBe($purchase->id)
        ->and($payment->type)->toBe(PaymentType::payment)
        ->and($payment->purchase_id)->toBe($purchase->id)
        ->and($payment->party_id)->toBe($supplier->id)
        ->and($payment->document_number)->toStartWith('PAY-');
});

test('purchase status is paid or pending based on payment', function (float $paid, DocumentStatus $expected) {
    $user = User::factory()->create();
    $supplier = Party::factory()->supplier()->create();
    $warehouse = Warehouse::factory()->create();
    Cashbox::factory()->create();
    $product = Product::factory()->create(['purchase_price' => 50]);

    Livewire::actingAs($user)->test('livewire.purchase.create')
        ->set('partyId', $supplier->id)
        ->set('warehouseId', $warehouse->id)
        ->set('items.0.product_id', $product->id)
        ->set('items.0.quantity', 1)
        ->set('items.0.unit_price', 50)
        ->set('paidAmount', $paid)
        ->call('save')
        ->assertHasNoErrors();

    expect(Purchase::query()->firstOrFail()->status)->toBe($expected);
})->with([
    'unpaid' => [0, DocumentStatus::PENDING],
    'fully paid' => [50, DocumentStatus::PAID],
]);

test('service product lines are saved without stock movements', function () {
    $user = User::factory()->create();
    $supplier = Party::factory()->supplier()->create();
    $warehouse = Warehouse::factory()->create();
    $service = Product::factory()->service()->create(['purchase_price' => 90]);

    Livewire::actingAs($user)->test('livewire.purchase.create')
        ->set('partyId', $supplier->id)
        ->set('warehouseId', $warehouse->id)
        ->set('items.0.product_id', $service->id)
        ->set('items.0.quantity', 1)
        ->set('items.0.unit_price', 90)
        ->call('save')
        ->assertHasNoErrors();

    expect(Purchase::query()->firstOrFail()->items)->toHaveCount(1)
        ->and(StockMovement::query()->count())->toBe(0);
});

test('inactive products cannot be added from search', function () {
    $product = Product::factory()->create(['name' => 'Retired Widget', 'is_active' => false]);

    Livewire::actingAs(User::factory()->create())->test('livewire.purchase.create')
        ->call('addProductFromSearch', $product->id)
        ->assertSet('searchResults', []);

    expect(Livewire::actingAs(User::factory()->create())->test('livewire.purchase.create')
        ->instance()->items[0]['product_id'])->toBeNull();
});
```

Add any new copy keys used by the form to `lang/en/purchases.php` (title, subtitle, details, items, add_item, product, search_product, supplier, select_supplier, warehouse, select_warehouse, cashbox, select_cashbox, draft, save, qty, unit_price, discount, tax_rate, summary, notes, no_products, insufficient stock is not needed).

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/PurchasesCreateTest.php --compact`
Expected: FAIL — component not found and route not defined.

- [ ] **Step 3: Implement the component and route**

Create the SFC with the exact public API from the Interfaces block. Reuse the sale component's computation helpers, but: `supplierOptions()` filters active parties to `supplier|both`; search results expose `purchase_price`; `addProductFromSearch()` ignores inactive products; `rules()` makes `partyId` required and applies payment-field rules only when `! $isDraft && paidFloat() > 0`; `save()` follows spec section C step-by-step. Add `Route::livewire('/purchases/create', 'livewire.purchase.create')->name('purchases.create')`.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/PurchasesCreateTest.php --compact`
Expected: PASS (all tests in the file).

- [ ] **Step 5: Commit (skip if the user asked to keep changes uncommitted)**

`git add resources/views/components/livewire/purchase/⚡create.blade.php routes/web.php lang/en/purchases.php tests/Feature/PurchasesCreateTest.php && git commit -m "feat(purchases): add new purchase form"`

---

### Task 4: Purchases list and sidebar link

**Files:**
- Create: `resources/views/components/livewire/purchase/⚡index.blade.php`
- Create: `tests/Feature/PurchasesIndexTest.php`
- Modify: `resources/views/components/layout/sidebar.blade.php`, `lang/en/purchases.php`, `routes/web.php`

**Interfaces:**
- Consumes: `Purchase::query()->with(['party', 'warehouse'])`, `Warehouse::query()`, named preview/create routes.
- Produces: full-page component `livewire.purchase.index` at named route `purchases.index` with `#[Url] public string $search = ''`, `#[Url] public string $warehouse = ''`, and computed `purchases()` returning a 10-per-page paginator.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/PurchasesIndexTest.php` with `uses(RefreshDatabase::class)`:

```php
test('purchases index route exists and requires auth', function () {
    expect(route('purchases.index'))->toBe(url('/purchases'));

    $this->get(route('purchases.index'))->assertRedirect();
});

test('purchases index lists purchases with supplier warehouse totals and status', function () {
    $user = User::factory()->create();
    $supplier = Party::factory()->supplier()->create(['name' => 'Acme Supplier']);
    $warehouse = Warehouse::factory()->create(['name' => 'Main Store']);
    $purchase = Purchase::factory()->for($supplier, 'party')->for($warehouse)->create([
        'document_number' => 'PUR-LIST',
        'status' => DocumentStatus::PAID,
    ]);

    $html = Livewire::actingAs($user)->test('livewire.purchase.index')
        ->assertSee('PUR-LIST')
        ->assertSee('Acme Supplier')
        ->assertSee('Main Store')
        ->html();

    expect($html)->toContain(route('purchases.invoice_preview', $purchase));
});

test('purchases search matches document number and supplier name', function (string $value) {
    $user = User::factory()->create();
    Purchase::factory()->create(['document_number' => 'PUR-AAA']);
    Purchase::factory()->for(Party::factory()->supplier()->create(['name' => 'Beta Supplier']), 'party')->create();

    Livewire::actingAs($user)->test('livewire.purchase.index')
        ->set('search', $value)
        ->assertSee($value);
})->with(['PUR-AAA', 'Beta Supplier']);

test('purchases warehouse filter limits results', function () {
    $user = User::factory()->create();
    $first = Warehouse::factory()->create(['name' => 'First Depot']);
    $second = Warehouse::factory()->create(['name' => 'Second Depot']);
    Purchase::factory()->for($first)->create(['document_number' => 'PUR-FIRST']);
    Purchase::factory()->for($second)->create(['document_number' => 'PUR-SECOND']);

    Livewire::actingAs($user)->test('livewire.purchase.index')
        ->set('warehouse', (string) $second->id)
        ->assertSee('PUR-SECOND')
        ->assertDontSee('PUR-FIRST');
});

test('purchases index paginates at ten newest first', function () {
    $user = User::factory()->create();
    foreach (range(1, 12) as $number) {
        Purchase::factory()->create([
            'document_number' => sprintf('PUR-%02d', $number),
            'invoice_date' => '2026-01-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
        ]);
    }

    $component = Livewire::actingAs($user)->test('livewire.purchase.index');

    expect($component->html())->toContain('PUR-12')->not->toContain('PUR-01');

    $component->call('gotoPage', 2);

    expect($component->html())->toContain('PUR-01')->not->toContain('PUR-12');
});

test('purchases index escapes supplier names', function () {
    $user = User::factory()->create();
    $supplier = Party::factory()->supplier()->create(['name' => '<script>alert(1)</script>']);
    Purchase::factory()->for($supplier, 'party')->create();

    $html = Livewire::actingAs($user)->test('livewire.purchase.index')->html();

    expect($html)->not->toContain('<script>alert(1)</script>')->toContain('&lt;script&gt;');
});
```

Add list copy keys to `lang/en/purchases.php`: `list_title`, `list_subtitle`, `new_purchase`, `all_warehouses`, `date`, `total`, `paid`, `remaining`, `status`, `actions`, `no_records`.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/PurchasesIndexTest.php --compact`
Expected: FAIL — component not found and route not defined.

- [ ] **Step 3: Implement the index, route, and sidebar link**

Build the component per the Interfaces block and spec section D. Replace the sidebar `Purchases` entry's `'#'` with `route('purchases.index')`, set `'active' => request()->routeIs('purchases.*')` and `'navigate' => true`; keep the existing icon path. Add `Route::livewire('/purchases', 'livewire.purchase.index')->name('purchases.index')`.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/PurchasesIndexTest.php --compact`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit (skip if the user asked to keep changes uncommitted)**

`git add resources/views/components/livewire/purchase/⚡index.blade.php resources/views/components/layout/sidebar.blade.php routes/web.php lang/en/purchases.php tests/Feature/PurchasesIndexTest.php && git commit -m "feat(purchases): add purchases list and navigation"`

---

### Task 5: Link purchases from party transaction history

**Files:**
- Modify: `resources/views/components/livewire/party/⚡show.blade.php`
- Test: `tests/Feature/PurchaseInvoicePreviewTest.php`

**Interfaces:**
- Consumes: `purchases.invoice_preview` route from Task 2, transaction objects produced by `allTransactions()` in the party show component.
- Produces: purchase rows in party history link to their invoice preview; sale rows keep their existing preview link.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/PurchaseInvoicePreviewTest.php`:

```php
test('party transaction history links purchase documents to the preview', function () {
    $user = User::factory()->create();
    $supplier = Party::factory()->supplier()->create();
    $purchase = Purchase::factory()->for($supplier, 'party')->create([
        'document_number' => 'PUR-PARTY',
    ]);

    $html = Livewire::actingAs($user)->test('livewire.party.show', ['party' => $supplier])->html();

    expect($html)->toContain('PUR-PARTY')
        ->toContain(route('purchases.invoice_preview', $purchase));
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Feature/PurchaseInvoicePreviewTest.php --filter='party transaction history' --compact`
Expected: FAIL — URL not present in the rendered HTML.

- [ ] **Step 3: Add the purchase link**

Extend the transaction objects in `allTransactions()` with a nullable `purchase_id`, set it to the purchase id for purchase rows and `null` elsewhere. In the document-number cell, link to `route('purchases.invoice_preview', $transaction->purchase_id)` when set; keep the sale preview link as the first branch. Update the object-shape PHPDoc.

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Feature/PurchaseInvoicePreviewTest.php --compact`
Expected: PASS (all tests in the file).

- [ ] **Step 5: Commit (skip if the user asked to keep changes uncommitted)**

`git add resources/views/components/livewire/party/⚡show.blade.php tests/Feature/PurchaseInvoicePreviewTest.php && git commit -m "feat(purchases): link purchase history to invoice preview"`

---

### Task 6: Full verification

**Files:** none

- [ ] **Step 1: Run the focused suites**

Run: `vendor/bin/pest tests/Feature/PurchasesCreateTest.php tests/Feature/PurchasesIndexTest.php tests/Feature/PurchaseInvoicePreviewTest.php --compact`
Expected: PASS with no failures.

- [ ] **Step 2: Run the full suite**

Run: `vendor/bin/pest --compact`
Expected: PASS with no regressions.

- [ ] **Step 3: Check formatting and static analysis**

Run: `vendor/bin/pint --dirty --format agent`, then `vendor/bin/phpstan analyse --no-progress`
Expected: Pint reports no changes; PHPStan reports no errors.

- [ ] **Step 4: Rebuild frontend assets**

Run: `pnpm run build`
Expected: Vite build completes successfully.
