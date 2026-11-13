# Blade Components Reference

Reusable Blade components for the MiniPOS UI live in `resources/views/components/`. They are anonymous Blade components — no PHP classes — used as HTML tags: `<x-card>`, `<x-button>`, etc.

All components are styled with Tailwind CSS v4 and use Alpine.js for interactivity. Extra HTML attributes (class, id, `@click`, `wire:navigate`, …) are always merged onto the root element, so you can extend any component without modifying it.

---

## Layout

### `<x-layout>`

Standard application shell: sidebar, header, and content area. Every authenticated page should be wrapped in it. Content is centered via the wrapping `<main>`; pages provide their own `max-w-*` container. The tab title comes from `config('app.name')`.

```blade
<x-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        {{-- page content --}}
    </div>
</x-layout>
```

Optionally set the browser tab title:

### `<x-print-layout>`

Minimal shell for printable pages (no sidebar/header). Includes `@media print` rules: elements with class `no-print` are hidden when printing and the print sheet loses its shadow/margins. Used by the invoice preview.

```blade
<x-print-layout :title="__('sales.print_preview')">
    <div class="no-print">Toolbar — hidden when printing</div>
    <div class="print-sheet">Printable content</div>
</x-print-layout>
```

---

## Page Structure

### `<x-page-header>`

Page title with optional subtitle and an `actions` slot on the right (buttons, modals, …).

| Prop | Required | Default | Description |
|---|---|---|---|
| `title` | ✔ | — | Heading text |
| `subtitle` | | `null` | Muted text under the heading |

```blade
<x-page-header :title="__('users.title')" :subtitle="__('users.subtitle')">
    <x-slot:actions>
        <x-button>{{ __('users.add') }}</x-button>
    </x-slot:actions>
</x-page-header>
```

The actions slot is wrapped in a flex container with `gap-3` and only renders when present.

### `<x-card>`

Card shell (`rounded-xl border bg-white shadow-sm`) used to group content. The header row (title + actions) only renders when a `title` prop is given **or** an `actions` slot is present — cards without a header render a flush body.

| Prop | Required | Default | Description |
|---|---|---|---|
| `title` | | `null` | Header heading; header hidden when omitted |

```blade
{{-- With header --}}
<x-card :title="__('reports.tab_inventory')">
    {{-- body content --}}
</x-card>

{{-- Header with action button --}}
<x-card :title="__('sales.items')">
    <x-slot:actions>
        <button type="button" @click="addItem">…</button>
    </x-slot:actions>
    {{-- body --}}
</x-card>

{{-- Headerless (flush body, e.g. a table) --}}
<x-card>
    <x-table>…</x-table>
</x-card>
```

> **Note:** the card body has **no default padding** — add `p-5` (or similar) to your content so tables can sit flush against the edges.

### `<x-stat-card>`

Dashboard stat tile: colored icon + label + value.

| Prop | Required | Default | Description |
|---|---|---|---|
| `label` | ✔ | — | Small muted label |
| `value` | ✔ | — | Big bold value |
| `path` | ✔ | — | SVG `d` attribute of a Heroicons outline icon |
| `color` | | `indigo` | Icon tint: `indigo`, `emerald`, `amber`, `sky` |

```blade
<x-stat-card
    :label="__('dashboard.todays_sales')"
    :value="number_format($todaysSales, 2)"
    color="indigo"
    path="M2.25 3h1.386c.51 0 .955.343…"
/>
```

---

## Buttons & Badges

### `<x-button>`

Styled button or link. Renders a `<button type="button">` by default, or an `<a>` when `href` is provided.

| Prop | Required | Default | Description |
|---|---|---|---|
| `variant` | | `primary` | `primary` (indigo filled), `secondary` (white bordered), `danger` (rose filled), `ghost` (text only) |
| `href` | | `null` | When set, renders an `<a>` link instead of a button |

All attributes pass through — combine freely with Alpine and Livewire:

```blade
<x-button>{{ __('general.save') }}</x-button>

<x-button variant="secondary" :href="route('sales.invoice_preview')" wire:navigate>
    {{ __('sales.preview_invoice') }}
</x-button>

<x-button variant="danger" @click="delete">Delete</x-button>

<x-button variant="ghost" :title="__('sales.sample_notice')">…</x-button>
```

> Buttons that need custom styling not covered by the variants (e.g. toggles with an Alpine `:class` active state) are written by hand — don't force them into `<x-button>`.

### `<x-badge>`

Small rounded status/label pill.

| Prop | Required | Default | Description |
|---|---|---|---|
| `color` | | `slate` | `emerald`, `amber`, `rose`, `sky`, `indigo`, `slate` |

```blade
<x-badge color="emerald">{{ $sale->status->label() }}</x-badge>
<x-badge color="amber" class="mt-2">PARTIAL</x-badge>
```

---

## Tables

### `<x-table>`

Table shell: horizontal-scroll wrapper, consistent `<table>` styling, uppercase header, divided rows. The optional `head` slot is rendered inside `<thead>`; the default slot inside `<tbody>`. Rows are written at the call site.

```blade
<x-table>
    @slot('head')
        <tr>
            <x-table.th>{{ __('users.name') }}</x-table.th>
            <x-table.th align="right">{{ __('dashboard.total') }}</x-table.th>
        </tr>
    @endslot

    @foreach ($sales as $sale)
        <tr class="transition hover:bg-slate-50">
            <x-table.td class="font-medium text-slate-900">{{ $sale->document_number }}</x-table.td>
            <x-table.td align="right">{{ number_format($sale->total_amount, 2) }}</x-table.td>
        </tr>
    @endforeach
</x-table>
```

### `<x-table.th>` / `<x-table.td>`

Header and body cells with consistent padding (`px-4 py-3`).

| Prop | Required | Default | Description |
|---|---|---|---|
| `align` | | `left` | `left`, `center`, `right` |

Additional classes merge onto the cell (e.g. `class="px-5"` for the dashboard's wider padding).

### `<x-empty-state>`

Centered muted message for empty tables/lists.

| Prop | Required | Default | Description |
|---|---|---|---|
| `message` | ✔ | — | Text to display |

```blade
@if ($recentSales->isEmpty())
    <x-empty-state :message="__('dashboard.no_sales')" />
@else
    <x-table>…</x-table>
@endif
```

---

## Datatables (Grid.js)

### `<x-grid-table>`

Mount point for a vanilla-JS [Grid.js](https://gridjs.io) table (search, sort, pagination — **no jQuery**). The component embeds the data as JSON; initialization and theming happen in `resources/js/app.js`.

| Prop | Required | Default | Description |
|---|---|---|---|
| `columns` | ✔ | — | Array of column titles |
| `data` | ✔ | — | Array of rows (arrays of cells) |
| `dateColumnIndex` | | `null` | Index of the date column used by `data-gridjs-filter` inputs |
| `show` | | `'true'` | Raw Alpine expression; the grid initializes lazily when it first becomes truthy (keeps column widths correct in hidden tabs) |

Any extra attributes pass through — use `id` to target the grid with filter inputs.

```blade
<x-grid-table
    id="grid-sales"
    :columns="$salesData['columns']"
    :data="$salesData['data']"
    :dateColumnIndex="$salesData['dateColumnIndex']"
    show="tab === 'sales'"
/>
```

**Date-range filtering:** add two date inputs bound to the grid with `data-gridjs-filter` (a selector for the mount) and `data-gridjs-bound` (`from` or `to`):

```blade
<input type="date" data-gridjs-filter="#grid-sales" data-gridjs-bound="from" />
<input type="date" data-gridjs-filter="#grid-sales" data-gridjs-bound="to" />
```

**Grid.js Tailwind theme** lives in `app.js` (`gridClassName`) — edit there to restyle tables globally.

---

## Forms

All form components share the same behavior: label, error display (via Laravel `$errors`), optional help text, and consistent styling. Pass extra attributes (e.g. `x-model`, `wire:model`) freely.

### `<x-form.input>`

| Prop | Required | Default | Description |
|---|---|---|---|
| `name` | ✔ | — | Input `name`/`id` |
| `label` | ✔ | — | Field label |
| `type` | | `text` | Any HTML input type |
| `help` | | `null` | Help text under the field (shown when there's no error) |
| `value` | | — | Pre-filled value |

```blade
<x-form.input name="invoice_date" type="date" :label="__('sales.invoice_date')" :value="now()->format('Y-m-d')" />
```

### `<x-form.select>`

| Prop | Required | Default | Description |
|---|---|---|---|
| `name` | ✔ | — | Select `name`/`id` |
| `label` | ✔ | — | Field label |
| `options` | ✔ | — | `value => label` array |
| `selected` | | `null` | Pre-selected value |
| `help` | | `null` | Help text |

```blade
<x-form.select
    name="role"
    :label="__('users.role')"
    :options="['admin' => __('users.role_admin'), 'cashier' => __('users.role_cashier')]"
    selected="cashier"
/>
```

### `<x-form.textarea>`

| Prop | Required | Default | Description |
|---|---|---|---|
| `name` | ✔ | — | Textarea `name`/`id` |
| `label` | ✔ | — | Field label |
| `rows` | | `3` | Row count |
| `help` | | `null` | Help text |

### `<x-form.checkbox>`

| Prop | Required | Default | Description |
|---|---|---|---|
| `name` | ✔ | — | Checkbox `name`/`id` |
| `label` | ✔ | — | Label shown next to the checkbox |
| `checked` | | `false` | Initial checked state |

---

## Modal

### `<x-modal>`

Alpine-powered dialog with overlay, transitions, ESC-to-close, and body scroll lock.

| Prop | Required | Default | Description |
|---|---|---|---|
| `maxWidth` | | `md` | `sm`, `md`, `lg`, `xl`, `2xl` |

| Slot | Purpose |
|---|---|
| `trigger` | Element that opens the modal (usually an `<x-button @click="open = true">`) |
| `title` | Modal heading |
| *(default)* | Body content |
| `footer` | Bottom action bar (right-aligned, only rendered when present) |

The modal exposes an Alpine `open` state — reference it from the trigger and footer with `@click="open = true"` / `open = false"`.

```blade
<x-modal>
    <x-slot:trigger>
        <x-button @click="open = true">{{ __('users.add') }}</x-button>
    </x-slot:trigger>

    <x-slot:title>{{ __('users.add') }}</x-slot:title>

    <form class="space-y-4">
        <x-form.input name="name" :label="__('users.name')" />
        <x-form.checkbox name="is_active" :label="__('users.active')" checked />
    </form>

    <x-slot:footer>
        <x-button variant="secondary" @click="open = false">{{ __('general.cancel') }}</x-button>
        <x-button @click="open = false">{{ __('general.save') }}</x-button>
    </x-slot:footer>
</x-modal>
```

> Inside a Livewire component, prefer `wire:navigate`-safe placement and keep `wire:key` on repeated elements.

---

## Putting It Together

A typical page:

```blade
<x-layout>
    <div class="mx-auto max-w-7xl space-y-6" x-data="{ tab: 'sales' }">
        <x-page-header :title="__('reports.title')" :subtitle="__('reports.subtitle')" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-stat-card … />
        </div>

        <x-card :title="__('reports.tab_sales')">
            <div class="px-5 pb-5">
                <x-grid-table id="grid-sales" :columns="$cols" :data="$rows" show="tab === 'sales'" />
            </div>
        </x-card>
    </div>
</x-layout>
```

## Conventions

- **Translations:** always pass labels through `__()` with keys in `lang/en/*.php`; never hardcode UI text in views.
- **Slots are conditional:** `actions`/`footer`/`head` slots only render when provided.
- **No default body padding on `<x-card>`** — padding belongs to the caller so tables can be flush.
- **Attributes merge last:** caller classes win by overriding; avoid fighting component defaults where a variant/prop exists.
- **Interactivity is Alpine or Livewire only** — no jQuery; datatables use Grid.js.
