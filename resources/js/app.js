// Livewire + Alpine (single Alpine instance, started by Livewire).
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm'

import { Grid } from 'gridjs'
import 'gridjs/dist/theme/mermaid.css'

window.Alpine = Alpine

const gridClassName = {
    table: 'w-full text-left text-sm',
    th: 'bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500 text-left',
    td: 'px-4 py-3 border-t border-slate-100 text-slate-600',
    footer: 'flex items-center justify-between px-4 py-3 text-sm text-slate-500',
    paginationButton: 'rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500/30',
    paginationButtonCurrent: 'rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white',
    searchInput: 'block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 sm:w-64',
}

function applyFilters(mount, attempt = 0) {
    const store = mount._gridStore
    if (!store || !store.grid) {
        return
    }

    // Grid.js renders asynchronously; wait until the container is populated
    // before calling forceRender() on it.
    if (mount.childElementCount === 0) {
        if (attempt < 10) {
            requestAnimationFrame(() => applyFilters(mount, attempt + 1))
        }
        return
    }

    const rows = !store.from && !store.to
        ? store.allRows
        : store.allRows.filter((row) => (!store.from || row[store.dateColumnIndex] >= store.from) && (!store.to || row[store.dateColumnIndex] <= store.to))

    store.grid.updateConfig({ data: rows }).forceRender()
}

function initGrid(mount) {
    // Same-session repeat dispatches (e.g. Alpine re-evaluating x-effect).
    if (mount.dataset.gridjsReady && mount._gridStore) {
        return
    }

    // wire:navigate restores grid markup from snapshots but not our JS state
    // (expando properties are not cloned), so rebuild from the embedded config.
    mount.replaceChildren()
    mount.dataset.gridjsReady = '1'

    const config = JSON.parse(mount.getAttribute('data-gridjs'))
    const grid = new Grid({
        columns: config.columns,
        data: config.data,
        search: true,
        sort: true,
        autoWidth: false,
        fixedHeader: false,
        pagination: { limit: 5, summary: false },
        className: gridClassName,
        language: {
            search: {
                placeholder: '🔍 Search…',
            },
        },
    }).render(mount)

    mount._gridStore = {
        grid,
        allRows: config.data,
        dateColumnIndex: config.dateColumnIndex ?? null,
        from: '',
        to: '',
    }
}

// Grids initialize lazily: tab panels dispatch 'gridjs-init' via x-effect
// once they become visible (keeps column widths correct in hidden panels).
document.addEventListener('gridjs-init', (event) => {
    const mount = event.target.closest('[data-gridjs]')
    if (mount) {
        initGrid(mount)
    }
})

document.querySelectorAll('[data-gridjs-filter]').forEach((input) => {
    input.addEventListener('change', () => {
        const mount = document.querySelector(input.dataset.gridjsFilter)
        if (!mount) {
            return
        }

        mount._gridStore ||= {
            grid: null,
            allRows: JSON.parse(mount.getAttribute('data-gridjs')).data,
            dateColumnIndex: JSON.parse(mount.getAttribute('data-gridjs')).dateColumnIndex ?? null,
            from: '',
            to: '',
        }
        mount._gridStore[input.dataset.gridjsBound] = input.value

        applyFilters(mount)
    })
})

Livewire.start()
