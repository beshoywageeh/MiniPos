<x-layout>
    @php
        $salesStats = [
            ['label' => __('reports.total_sales'), 'value' => '$12,480.75', 'color' => 'indigo', 'path' => 'M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.443 12.9 12.998 12.75 12 12.75c-.532 0-1.057-.112-1.55-.335a3.378 3.378 0 0 1-.936-.635 3.106 3.106 0 0 1-.664-.936 3.323 3.323 0 0 1-.35-1.52V9.75c0-.531.116-1.057.35-1.55a3.106 3.106 0 0 1 .664-.936c.27-.27.587-.484.936-.635.493-.223 1.018-.335 1.55-.335.998 0 2.057.15 2.878.879l.879.659M12 6v12m8.25-6a8.25 8.25 0 1 1-16.5 0 8.25 8.25 0 0 1 16.5 0Z'],
            ['label' => __('reports.invoices_count'), 'value' => '86', 'color' => 'sky', 'path' => 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z'],
            ['label' => __('reports.avg_invoice'), 'value' => '$145.12', 'color' => 'emerald', 'path' => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.096c.711.198 1.434.342 2.158.457M18.75 18.75V18a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 18v.75m8.25-13.5h6a3 3 0 0 1 3 3v13.5a3 3 0 0 1-3 3h-15a3 3 0 0 1-3-3V8.25a3 3 0 0 1 3-3h6M15.75 6v3.75m0 0v3.75m0-3.75h3.75m-3.75 0H12'],
        ];

        $salesByDay = [
            ['Mon', 820.50], ['Tue', 540.25], ['Wed', 1130.75], ['Thu', 760.00], ['Fri', 1980.25], ['Sat', 2450.00], ['Sun', 1490.50],
        ];
        $maxDaySales = max(array_column($salesByDay, 1));

        $salesRows = [
            ['INV-000086', '2026-11-12', 'John Smith', 'PAID', '$165.00'],
            ['INV-000085', '2026-11-12', 'Sara Johnson', 'PARTIAL', '$89.50'],
            ['INV-000084', '2026-11-11', 'Walk-in Customer', 'PAID', '$32.75'],
            ['INV-000083', '2026-11-10', 'John Smith', 'PAID', '$410.20'],
            ['INV-000082', '2026-11-09', 'Mike Brown', 'PARTIAL', '$120.00'],
            ['INV-000081', '2026-11-09', 'Sara Johnson', 'PAID', '$75.30'],
            ['INV-000080', '2026-11-08', 'Walk-in Customer', 'DRAFT', '$210.00'],
            ['INV-000079', '2026-11-07', 'Mike Brown', 'PAID', '$96.45'],
        ];
        $salesData = [
            'columns' => [__('sales.invoice_number'), __('dashboard.recent_sales'), __('sales.customer'), __('dashboard.status'), __('dashboard.total')],
            'data' => $salesRows,
            'dateColumnIndex' => 1,
        ];

        $inventoryRows = [
            ['Espresso Coffee 250g', 'SKU-00123', 'Main Warehouse', '5.000', '10.000', '$42.50'],
            ['Green Tea 100g', 'SKU-00456', 'Main Warehouse', '48.000', '5.000', '$249.60'],
            ['Ceramic Mug', 'SKU-00789', 'Downtown Store', '120.000', '20.000', '$480.00'],
            ['Honey Jar 500g', 'SKU-00321', 'Downtown Store', '3.000', '8.000', '$36.00'],
            ['Black Pepper 50g', 'SKU-00654', 'Main Warehouse', '64.000', '10.000', '$128.00'],
            ['Olive Oil 1L', 'SKU-00741', 'Main Warehouse', '2.000', '6.000', '$54.00'],
            ['Sugar 1kg', 'SKU-00210', 'Downtown Store', '90.000', '15.000', '$135.00'],
        ];
        $inventoryData = [
            'columns' => [__('reports.product'), __('reports.sku'), __('reports.warehouse'), __('reports.stock'), __('reports.min_stock'), __('reports.value')],
            'data' => $inventoryRows,
        ];

        $expenseRows = [
            ['EXP-000041', '2026-11-12', 'Rent', 'November store rent', '$800.00'],
            ['EXP-000040', '2026-11-10', 'Utilities', 'Electricity bill', '$96.40'],
            ['EXP-000039', '2026-11-08', 'Supplies', 'Packaging bags and rolls', '$45.75'],
            ['EXP-000038', '2026-11-05', 'Marketing', 'Local flyer campaign', '$150.00'],
            ['EXP-000037', '2026-11-02', 'Utilities', 'Internet and phone', '$62.10'],
            ['EXP-000036', '2026-10-31', 'Salaries', 'Part-time staff wages', '$920.00'],
            ['EXP-000035', '2026-10-28', 'Supplies', 'Receipt paper and ink', '$28.90'],
            [__('reports.total_label'), '', '', '', '$2,103.15'],
        ];
        $expenseData = [
            'columns' => [__('general.created_at'), __('reports.category'), __('reports.description'), __('dashboard.total')],
            'data' => $expenseRows,
            'dateColumnIndex' => 0,
        ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-6" x-data="{ tab: 'sales' }">
        <x-page-header :title="__('reports.title')" :subtitle="__('reports.subtitle')" />

        <div class="flex gap-2">
            @foreach (['sales' => __('reports.tab_sales'), 'inventory' => __('reports.tab_inventory'), 'expenses' => __('reports.tab_expenses')] as $tabKey => $tabLabel)
                <button
                    type="button"
                    @click="tab = '{{ $tabKey }}'"
                    :class="tab === '{{ $tabKey }}' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50'"
                    class="rounded-lg px-4 py-2 text-sm font-medium shadow-sm transition focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                >
                    {{ $tabLabel }}
                </button>
            @endforeach
        </div>

        {{-- Sales summary --}}
        <div x-show="tab === 'sales'" class="space-y-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                @foreach ($salesStats as $stat)
                    <x-stat-card :label="$stat['label']" :value="$stat['value']" :color="$stat['color']" :path="$stat['path']" />
                @endforeach
            </div>

            <x-card :title="__('reports.sales_by_day')">
                <div class="flex h-44 items-end gap-3 p-5 pt-2">
                    @foreach ($salesByDay as [$day, $amount])
                        <div class="flex h-full flex-1 flex-col items-center justify-end gap-2">
                            <div
                                class="w-full rounded-t-lg bg-indigo-500/80 transition hover:bg-indigo-600"
                                style="height: {{ round($amount / $maxDaySales * 100) }}%"
                                title="{{ number_format($amount, 2) }}"
                            ></div>
                            <span class="text-xs text-slate-500">{{ $day }}</span>
                        </div>
                    @endforeach
                </div>
            </x-card>

            <x-card :title="__('reports.tab_sales')">
                <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-end">
                    <div>
                        <label for="sales-from" class="block text-xs font-medium text-slate-500">{{ __('reports.filter_from') }}</label>
                        <input type="date" id="sales-from" data-gridjs-filter="#grid-sales" data-gridjs-bound="from" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30" />
                    </div>
                    <div>
                        <label for="sales-to" class="block text-xs font-medium text-slate-500">{{ __('reports.filter_to') }}</label>
                        <input type="date" id="sales-to" data-gridjs-filter="#grid-sales" data-gridjs-bound="to" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30" />
                    </div>
                </div>
                <div class="px-5 pb-5">
                    <x-grid-table id="grid-sales" :columns="$salesData['columns']" :data="$salesData['data']" :dateColumnIndex="$salesData['dateColumnIndex']" show="tab === 'sales'" />
                </div>
            </x-card>
        </div>

        {{-- Inventory --}}
        <div x-show="tab === 'inventory'" x-cloak>
            <x-card :title="__('reports.tab_inventory')">
                <div class="p-5">
                    <x-grid-table id="grid-inventory" :columns="$inventoryData['columns']" :data="$inventoryData['data']" show="tab === 'inventory'" />
                </div>
            </x-card>
        </div>

        {{-- Expenses --}}
        <div x-show="tab === 'expenses'" x-cloak>
            <x-card :title="__('reports.tab_expenses')">
                <div class="p-5">
                    <x-grid-table id="grid-expenses" :columns="$expenseData['columns']" :data="$expenseData['data']" :dateColumnIndex="$expenseData['dateColumnIndex']" show="tab === 'expenses'" />
                </div>
            </x-card>
        </div>
    </div>
</x-layout>
