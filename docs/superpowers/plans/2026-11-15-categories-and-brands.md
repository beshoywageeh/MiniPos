# Categories & Brands Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Full CRUD management pages for categories (with subcategories) and brands, mirroring the existing users-page Livewire SFC pattern.

**Architecture:** Two new Livewire 4 SFC module trees under `resources/views/components/livewire/catalog/` — one for categories, one for brands — each with an `⚡index` page component and `actions/` child components for create/update/delete. Events (`category-created`, `brand-updated`, …) drive parent refresh (`#[On]`) and Alpine flash messages. Full-page routing via `Route::livewire()`. The existing empty `BrandController`/`CategoryController` scaffolds are left untouched — the app's UI pattern is Livewire SFCs.

**Tech Stack:** Laravel 12, Livewire 4.4 (SFC with `⚡` files), Blade components (`x-card`, `x-table`, `x-modal`, `x-form.input`, `x-button`), Pest.

**Spec:** Design decisions captured in "Design Decisions" below (no separate spec doc — this is a pattern-following build; the users module `resources/views/components/livewire/users/` is the reference implementation to copy).

## Design Decisions

- **Schema:** already exists — `categories` (code unique, name, description, parent_id → categories.id, is_active, soft deletes) and `brands` (code unique, name, description, is_active, soft deletes). No migrations. Factories exist (`CategoryFactory` uses `CAT-####` codes, `BrandFactory` uses `BRD-####`).
- **Categories support subcategories** (user decision): create/update forms get a nullable "Parent category" dropdown; the list shows the parent's name; a category cannot be its own parent (exclude self from the options).
- **Delete = soft delete with confirmation modal**, same UX as the users delete component. Schema/model already have SoftDeletes — no User-model issues here.
- **Search:** a name/code search box (`wire:model.live.debounce.300ms="search"` on the index, `#[Url]` property) filters via `where('name', 'like', ...)->orWhere('code', 'like', ...)`.

## Global Constraints

- Follow the users-module conventions exactly: anonymous classes in SFC files, `use` statements at top of SFC PHP block, `#[Computed]` for list queries, `#[On('...')]` for refresh listeners.
- Component names: `livewire.catalog.categories.index`, `livewire.catalog.categories.actions.create`, etc.; brands identical shape.
- Every SFC class must import every model/facade it uses (`User`-lesson: missing imports fail at runtime, not at render).
- Events are camelCase: `category-created`, `category-updated`, `category-deleted`, `brand-created`, `brand-updated`, `brand-deleted`.
- wire:key on every child component rendered in a loop: `wire:key="category-update-{{ $category->id }}"` shape.
- Run `vendor/bin/pint --dirty --format agent` before every commit; all Artisan/pest commands with `--no-interaction`.
- Lang strings under `lang/en/categories.php` / `lang/en/brands.php` (reuse `general.*` where it exists: cancel, save, delete, actions, created_at).
- Do NOT touch `app/Http/Controllers/BrandController.php` / `CategoryController.php`.

## Review Focus

- **Duplicate code → no unique constraint on code:** typing an existing code must be rejected by validation, not crash on the DB unique index — both on create and update (update must ignore its own row). *Tested in Tasks 3/4 (categories) and 6/7 (brands).*
- **Category self-parenting:** setting a category's parent to itself must be impossible (excluded from dropdown options). *Tested in Task 4.*
- **Deleted-row persistence in UI:** after create/update/delete, the list must reflect the change without a manual reload (`#[On]` listeners). *Tested in Tasks 2/3/4/5.*
- **Search with no matches:** empty search result shows the "no records" row, not an error. *Tested in Task 2.*
- **Events fired by child components:** `assertDispatched` on every action so the flash/refresh contract stays intact. *Tested in Tasks 3/4/5/6/7.*

---

### Task 1: Routes, lang files, sidebar links

**Files:**
- Modify: `routes/web.php` (inside the existing `Route::group(['middleware' => ['auth']], ...)` block)
- Modify: `resources/views/components/layout/sidebar.blade.php` (nav items array)
- Create: `lang/en/categories.php`, `lang/en/brands.php`
- Create: `lang/en/dashboard.php` nav keys only if the file already exists (check `grep -n nav_dashboard lang/en/dashboard.php`); otherwise add keys there as the sidebar currently pulls nav labels from it

**Interfaces:**
- Consumes: existing `Route::livewire()` pattern from `routes/web.php`, existing sidebar item shape (`['label', 'href', 'active', 'navigate', 'path']`)
- Produces: named routes `categories.index` and `brands.index`; component names `livewire.catalog.categories.index` / `livewire.catalog.brands.index` used by Tasks 2 and 6

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CatalogPagesTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('categories page is reachable and requires auth', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('categories.index'))
        ->assertOk()
        ->assertSee(__('categories.title'));

    $this->get(route('categories.index'))->assertRedirect();
});

test('brands page is reachable and requires auth', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('brands.index'))
        ->assertOk()
        ->assertSee(__('brands.title'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/CatalogPagesTest.php --compact`
Expected: FAIL — route `categories.index` not defined.

- [ ] **Step 3: Add routes, lang files, sidebar entries**

- Routes (match the users line style): `Route::livewire('/categories', 'livewire.catalog.categories.index')->name('categories.index');` and `Route::livewire('/brands', 'livewire.catalog.brands.index')->name('brands.index');`
- `lang/en/categories.php` / `lang/en/brands.php`: keys `title`, `subtitle`, `add`, `edit`, `delete`, `search`, `created`, `updated`, `deleted`, `code`, `name`, `description`, `active`, `parent` (categories only: `'parent' => 'Parent category'`), `no_records`.
- Sidebar: insert two items after the Users entry, copying the existing item shape with `route('categories.index')` / `route('brands.index')`, `request()->routeIs('categories.*')` / `'brands.*'`, and Heroicons outline paths (categories: tag icon `M9.568 3H5.25a2.25 2.25 0 0 0-2.25 2.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z`; brands: bookmark icon `M17.593 3.75c.176 0 .316.144.312.32a70.5 70.5 0 0 1-.85 6.035 71.5 71.5 0 0 1-4.215 16.244 74.3 74.3 0 0 1-4.215-16.244 70.5 70.5 0 0 1-.85-6.035.312.312 0 0 1 .312-.32h9.406Zm-1.137 0a74 74 0 0 0-.477 6.114 73 73 0 0 0 .477 6.114 73 73 0 0 0 .477-6.114 74 74 0 0 0-.477-6.114Z` — trim to `stroke-linecap="round"` style consistent with siblings).

Note: the components don't exist yet, so the pages will 500 until Task 2/6 create them — verify only the route-not-found failure is gone; the "view not found" failure is acceptable at this step.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/CatalogPagesTest.php --compact`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add routes/web.php lang/en/ tests/Feature/CatalogPagesTest.php resources/views/components/layout/sidebar.blade.php
git commit -m "feat(catalog): routes, lang and sidebar links for categories and brands"
```

---

### Task 2: Categories index page

**Files:**
- Create: `resources/views/components/livewire/catalog/categories/⚡index.blade.php`
- Modify: `tests/Feature/CatalogPagesTest.php` (append tests)

**Interfaces:**
- Consumes: `Category` model, `x-card`/`x-table`/`x-page-header` components, users `⚡index.blade.php` as template
- Produces: page component `livewire.catalog.categories.index` with computed `categories()` (`Category::query()->with('parent')->latest('id')->when($this->search, ...)->paginate(10)`), public `#[Url] public string $search = ''`, listeners `#[On('category-created')]`, `#[On('category-updated')]`, `#[On('category-deleted')]` on one `refreshList(): void` method; flash markup listening to all three events with `__('categories.created'/'updated'/'deleted')`

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/CatalogPagesTest.php`:

```php
test('categories page lists categories with parent names', function () {
    $user = User::factory()->create();
    $parent = \App\Models\Category::factory()->create(['name' => 'Beverages']);
    \App\Models\Category::factory()->create(['name' => 'Soda', 'parent_id' => $parent->id]);

    Livewire::actingAs($user)->test('livewire.catalog.categories.index')
        ->assertSee('Beverages')
        ->assertSee('Soda')
        ->assertSee('Beverages'); // parent column shows for Soda
});

test('categories search filters by name', function () {
    $user = User::factory()->create();
    \App\Models\Category::factory()->create(['name' => 'Beverages']);
    \App\Models\Category::factory()->create(['name' => 'Snacks']);

    Livewire::actingAs($user)->test('livewire.catalog.categories.index')
        ->set('search', 'Snacks')
        ->assertSee('Snacks')
        ->assertDontSee('Beverages');
});

test('categories list refreshes when an event fires', function () {
    $user = User::factory()->create();
    $target = \App\Models\Category::factory()->create(['name' => 'Before Update']);

    $component = Livewire::actingAs($user)
        ->test('livewire.catalog.categories.index');

    expect($component->html())->toContain('Before Update');

    $target->update(['name' => 'After Update']);

    $component->dispatch('category-updated');

    expect($component->html())->toContain('After Update');
});

test('categories search with no match shows empty state', function () {
    $user = User::factory()->create();
    \App\Models\Category::factory()->create(['name' => 'Beverages']);

    Livewire::actingAs($user)->test('livewire.catalog.categories.index')
        ->set('search', 'zzzz')
        ->assertSee(__('categories.no_records'));
});
```

(Add `use Livewire\Livewire;` to the imports.)

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/CatalogPagesTest.php --compact`
Expected: FAIL — component `livewire.catalog.categories.index` not found.

- [ ] **Step 3: Implement the index component**

Copy the structure of `resources/views/components/livewire/users/⚡index.blade.php` (it is the canonical template): anonymous class with `WithPagination`, `#[Computed] categories()`, `#[Url] public string $search = ''`, one `refreshList()` with all three `#[On]` attributes; view with flash `x-data="{ flash: false, flashMessage: '' }"` + three `@category-*.window` listeners, `x-page-header` (title/subtitle from `categories.*`), `x-card`/`x-table` with columns: #, Code, Name, Parent, Created, Actions; parent column renders `$category->parent?->name ?? '—'`; search input above the table via `x-form.input` with `wire:model.live.debounce.300ms="search"`, `name="search"`, `:label="__('categories.search')"`. Leave the Actions cell with a comment placeholder — filled by Tasks 3–5.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/CatalogPagesTest.php --compact`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/components/livewire/catalog/ tests/Feature/CatalogPagesTest.php
git commit -m "feat(categories): index page with search, pagination and event refresh"
```

---

### Task 3: Categories create component

**Files:**
- Create: `resources/views/components/livewire/catalog/categories/actions/⚡create.blade.php`
- Modify: `resources/views/components/livewire/catalog/categories/⚡index.blade.php` (page header slot)
- Modify: `tests/Feature/CatalogPagesTest.php` (append tests)

**Interfaces:**
- Consumes: users `actions/⚡create.blade.php` as template; `x-modal` (trigger slot + `@category-created.window="open = false"` on the tag); `Category::factory()`
- Produces: component `livewire.catalog.categories.actions.create` with public `string $code = ''`, `string $name = ''`, `string $description = ''`, `bool $isActive = true`, `public int|string $parentId = ''`; computed `parentOptions()` = `Category::orderBy('name')->pluck('name', 'id')`; `create(): void` validates (`code` required|string|max:20|unique:categories,code; `name` required|string|max:255; `description` nullable|string; `isActive` boolean; `parentId` nullable|integer|exists:categories,id), creates with `parent_id` when set, resets, dispatches `category-created`

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/CatalogPagesTest.php`:

```php
test('a category can be created', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.catalog.categories.actions.create')
        ->set('code', 'CAT-TEST')
        ->set('name', 'Beverages')
        ->call('create')
        ->assertDispatched('category-created');

    expect(\App\Models\Category::where('code', 'CAT-TEST')->where('name', 'Beverages')->exists())->toBeTrue();
});

test('category creation validates duplicate code and required name', function () {
    \App\Models\Category::factory()->create(['code' => 'CAT-TAKEN']);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.catalog.categories.actions.create')
        ->set('code', 'CAT-TAKEN')
        ->set('name', '')
        ->call('create')
        ->assertHasErrors(['code' => 'unique', 'name' => 'required'])
        ->assertNotDispatched('category-created');
});

test('a category can be created with a parent', function () {
    $user = User::factory()->create();
    $parent = \App\Models\Category::factory()->create(['name' => 'Root']);

    Livewire::actingAs($user)
        ->test('livewire.catalog.categories.actions.create')
        ->set('code', 'CAT-CHILD')
        ->set('name', 'Soda')
        ->set('parentId', $parent->id)
        ->call('create')
        ->assertDispatched('category-created');

    expect(\App\Models\Category::where('code', 'CAT-CHILD')->first()->parent_id)->toBe($parent->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/CatalogPagesTest.php --compact`
Expected: FAIL — component not found.

- [ ] **Step 3: Implement the create component**

Mirror users create: modal with trigger button (`__('categories.add')`, plus icon from the users create button), form fields `code` (text), `name`, `description` (textarea via `x-form.input` type default — check whether a textarea variant exists; if not, plain `<textarea>` with the same classes), checkbox for `isActive` (`wire:model` + Tailwind toggle classes), `parentId` `<select>` listing `parentOptions` with an empty "None" option, Cancel + Save buttons. Wire the component into the index page header: `<x-slot:actions><livewire:livewire.catalog.categories.actions.create /></x-slot:actions>`.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/CatalogPagesTest.php --compact`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/components/livewire/catalog/categories/ tests/Feature/CatalogPagesTest.php
git commit -m "feat(categories): create component with validation and event"
```

---

### Task 4: Categories update component

**Files:**
- Create: `resources/views/components/livewire/catalog/categories/actions/⚡update.blade.php`
- Modify: `resources/views/components/livewire/catalog/categories/⚡index.blade.php` (Actions cell)
- Modify: `tests/Feature/CatalogPagesTest.php` (append tests)

**Interfaces:**
- Consumes: users `actions/⚡update.blade.php` as template; index renders `<livewire:livewire.catalog.categories.actions.update :category="$category" wire:key="category-update-{{ $category->id }}" />`
- Produces: component `livewire.catalog.categories.actions.update` with `public Category $category`, pre-filled `code`/`name`/`description`/`isActive` from `mount(Category $category): void`; computed `parentOptions()` = `Category::whereKeyNot($this->category->id)->orderBy('name')->pluck('name', 'id')` (excludes self and soft-deleted via global scope); `update(): void` validates like create but `Rule::unique('categories', 'code')->ignore($this->category->id)`, updates, resets nothing except flash, dispatches `category-updated`

- [ ] **Step 1: Write the failing tests**

```php
test('a category can be updated including its parent', function () {
    $user = User::factory()->create();
    $target = \App\Models\Category::factory()->create(['name' => 'Old']);
    $parent = \App\Models\Category::factory()->create(['name' => 'Root']);

    Livewire::actingAs($user)
        ->test('livewire.catalog.categories.actions.update', ['category' => $target])
        ->assertSet('name', 'Old')
        ->set('name', 'New')
        ->set('parentId', $parent->id)
        ->call('update')
        ->assertDispatched('category-updated');

    expect($target->refresh()->name)->toBe('New')
        ->and($target->parent_id)->toBe($parent->id);
});

test('keeping own code passes unique validation', function () {
    $user = User::factory()->create();
    $target = \App\Models\Category::factory()->create(['code' => 'CAT-KEEP']);

    Livewire::actingAs($user)
        ->test('livewire.catalog.categories.actions.update', ['category' => $target])
        ->set('name', 'Renamed')
        ->call('update')
        ->assertHasNoErrors();

    expect($target->refresh()->code)->toBe('CAT-KEEP');
});

test('a category cannot select itself as parent', function () {
    $user = User::factory()->create();
    $target = \App\Models\Category::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('livewire.catalog.categories.actions.update', ['category' => $target]);

    expect($component->instance()->parentOptions->keys())->not->toContain($target->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/CatalogPagesTest.php --compact`
Expected: FAIL — component not found.

- [ ] **Step 3: Implement the update component**

Mirror users update. Public property `public int|string $parentId = '';` plus a `parentId` field; form gets a `<select wire:model="parentId">` listing `parentOptions` with an empty "None" option. Wire into the index Actions cell alongside create (delete comes in Task 5).

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/CatalogPagesTest.php --compact`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/components/livewire/catalog/categories/ tests/Feature/CatalogPagesTest.php
git commit -m "feat(categories): update component with parent selection and unique-ignore"
```

---

### Task 5: Categories delete component

**Files:**
- Create: `resources/views/components/livewire/catalog/categories/actions/⚡delete.blade.php`
- Modify: `resources/views/components/livewire/catalog/categories/⚡index.blade.php` (Actions cell)
- Modify: `tests/Feature/CatalogPagesTest.php` (append tests)

**Interfaces:**
- Consumes: users `actions/⚡delete.blade.php` as template (the trash-icon version); `SoftDeletes` already on `Category`
- Produces: component `livewire.catalog.categories.actions.delete` with `public Category $category`, `mount` stores it; `delete(): void` calls `$this->category->delete()` and dispatches `category-deleted`; index renders it with `wire:key="category-delete-{{ $category->id }}"`

- [ ] **Step 1: Write the failing tests**

```php
test('a category can be soft deleted', function () {
    $user = User::factory()->create();
    $target = \App\Models\Category::factory()->create(['name' => 'Doomed']);

    Livewire::actingAs($user)
        ->test('livewire.catalog.categories.actions.delete', ['category' => $target])
        ->call('delete')
        ->assertDispatched('category-deleted');

    expect($target->refresh()->deleted_at)->not->toBeNull()
        ->and(\App\Models\Category::where('name', 'Doomed')->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/CatalogPagesTest.php --compact`
Expected: FAIL — component not found.

- [ ] **Step 3: Implement the delete component**

Mirror the users delete component exactly: trash icon, `danger` variant submit, confirm modal, readonly name display (or plain text showing `$category->name`), Cancel + Delete. Wire into the Actions cell.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/CatalogPagesTest.php --compact`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/components/livewire/catalog/categories/ tests/Feature/CatalogPagesTest.php
git commit -m "feat(categories): soft delete with confirmation modal"
```

---

### Task 6: Brands index + create

**Files:**
- Create: `resources/views/components/livewire/catalog/brands/⚡index.blade.php`
- Create: `resources/views/components/livewire/catalog/brands/actions/⚡create.blade.php`
- Modify: `tests/Feature/CatalogPagesTest.php` (append tests)

**Interfaces:**
- Consumes: the completed categories module as the reference (brands are identical minus hierarchy); `Brand::factory()`
- Produces: `livewire.catalog.brands.index` (computed `brands()`, `#[Url] search`, three `#[On('brand-*')]` listeners, flash) and `livewire.catalog.brands.actions.create` (fields `code`, `name`, `description`, `isActive`; validation identical to categories create with `unique:brands,code`; dispatches `brand-created`)

- [ ] **Step 1: Write the failing tests**

```php
test('brands page lists brands', function () {
    $user = User::factory()->create();
    \App\Models\Brand::factory()->create(['name' => 'Acme Corp']);

    Livewire::actingAs($user)->test('livewire.catalog.brands.index')
        ->assertSee('Acme Corp');
});

test('a brand can be created and duplicate codes are rejected', function () {
    \App\Models\Brand::factory()->create(['code' => 'BRD-TAKEN']);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('livewire.catalog.brands.actions.create')
        ->set('code', 'BRD-NEW')
        ->set('name', 'Acme Corp')
        ->call('create')
        ->assertDispatched('brand-created');

    expect(\App\Models\Brand::where('code', 'BRD-NEW')->exists())->toBeTrue();

    Livewire::actingAs($user)
        ->test('livewire.catalog.brands.actions.create')
        ->set('code', 'BRD-TAKEN')
        ->set('name', 'Other')
        ->call('create')
        ->assertHasErrors(['code' => 'unique'])
        ->assertNotDispatched('brand-created');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/CatalogPagesTest.php --compact`
Expected: FAIL — components not found.

- [ ] **Step 3: Implement both components**

Categories index/create minus everything parent-related. Wire create into the header slot.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/CatalogPagesTest.php --compact`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/components/livewire/catalog/brands/ tests/Feature/CatalogPagesTest.php
git commit -m "feat(brands): index page and create component"
```

---

### Task 7: Brands update + delete

**Files:**
- Create: `resources/views/components/livewire/catalog/brands/actions/⚡update.blade.php`
- Create: `resources/views/components/livewire/catalog/brands/actions/⚡delete.blade.php`
- Modify: `resources/views/components/livewire/catalog/brands/⚡index.blade.php` (Actions cell)
- Modify: `tests/Feature/CatalogPagesTest.php` (append tests)

**Interfaces:**
- Consumes: categories update/delete components as templates; index renders both with `wire:key="brand-update-{{ $brand->id }}"` / `"brand-delete-{{ $brand->id }}"`
- Produces: `livewire.catalog.brands.actions.update` (`mount(Brand $brand)`, `Rule::unique('brands', 'code')->ignore($this->brand->id)`, dispatches `brand-updated`) and `livewire.catalog.brands.actions.delete` (`delete()`, dispatches `brand-deleted`)

- [ ] **Step 1: Write the failing tests**

```php
test('a brand can be updated and its own code passes unique validation', function () {
    $user = User::factory()->create();
    $target = \App\Models\Brand::factory()->create(['code' => 'BRD-KEEP', 'name' => 'Old']);

    Livewire::actingAs($user)
        ->test('livewire.catalog.brands.actions.update', ['brand' => $target])
        ->set('name', 'New')
        ->call('update')
        ->assertDispatched('brand-updated')
        ->assertHasNoErrors();

    expect($target->refresh()->name)->toBe('New')
        ->and($target->code)->toBe('BRD-KEEP');
});

test('a brand can be soft deleted', function () {
    $user = User::factory()->create();
    $target = \App\Models\Brand::factory()->create(['name' => 'Doomed']);

    Livewire::actingAs($user)
        ->test('livewire.catalog.brands.actions.delete', ['brand' => $target])
        ->call('delete')
        ->assertDispatched('brand-deleted');

    expect($target->refresh()->deleted_at)->not->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/CatalogPagesTest.php --compact`
Expected: FAIL — components not found.

- [ ] **Step 3: Implement both components**

Direct copies of the categories equivalents, s/category/brand/, no parent logic. Wire into the Actions cell.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/CatalogPagesTest.php --compact`
Expected: PASS.

- [ ] **Step 5: Full suite + commit**

```bash
vendor/bin/pest --compact
vendor/bin/pint --dirty --format agent
git add resources/views/components/livewire/catalog/ tests/Feature/CatalogPagesTest.php
git commit -m "feat(brands): update and soft delete components"
```

Expected: full suite PASS (all prior tests still green).

---

## Out of Scope (explicit)

- Linking products to categories/brands in the products UI (schema already supports it — separate plan).
- Deleting the empty `BrandController` / `CategoryController` scaffolds.
- User-module fixes from the earlier review (User `SoftDeletes` trait, `user-deleted` listener, delete tests) — separate task.
