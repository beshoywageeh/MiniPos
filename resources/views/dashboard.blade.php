<x-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <x-page-header :title="__('dashboard.welcome').', '.auth()->user()->name" :subtitle="__('dashboard.msg')" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card
                :label="__('dashboard.todays_sales')"
                :value="number_format($todaysSales, 2)"
                color="indigo"
                path="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"
            />
            <x-stat-card
                :label="__('dashboard.todays_purchases')"
                :value="number_format($todaysPurchases, 2)"
                color="sky"
                path="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m8.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h4.5M20.25 18.75a1.5 1.5 0 0 0 1.5-1.5V13.5L18 9m2.25 9.75H21a.75.75 0 0 0 .75-.75V14.25a2.25 2.25 0 0 0-2.25-2.25h-1.5M3.75 14.25h4.5m-4.5 0V8.25A1.125 1.125 0 0 1 4.875 7.5H6.75V6m0 0h6V4.5h-6V6m6 0v9.75"
            />
            <x-stat-card
                :label="__('dashboard.todays_expenses')"
                :value="number_format($todaysExpenses, 2)"
                color="amber"
                path="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.443 12.9 12.998 12.75 12 12.75c-.532 0-1.057-.112-1.55-.335a3.378 3.378 0 0 1-.936-.635 3.106 3.106 0 0 1-.664-.936 3.323 3.323 0 0 1-.35-1.52V9.75c0-.531.116-1.057.35-1.55a3.106 3.106 0 0 1 .664-.936c.27-.27.587-.484.936-.635.493-.223 1.018-.335 1.55-.335.998 0 2.057.15 2.878.879l.879.659M12 6v12m8.25-6a8.25 8.25 0 1 1-16.5 0 8.25 8.25 0 0 1 16.5 0Z"
            />
            <x-stat-card
                :label="__('dashboard.cash_balance')"
                :value="number_format($cashBalance, 2)"
                color="emerald"
                path="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.096c.711.198 1.434.342 2.158.457M18.75 18.75V18a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 18v.75m8.25-13.5h6a3 3 0 0 1 3 3v13.5a3 3 0 0 1-3 3h-15a3 3 0 0 1-3-3V8.25a3 3 0 0 1 3-3h6M15.75 6v3.75m0 0v3.75m0-3.75h3.75m-3.75 0H12"
            />
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <x-card :title="__('dashboard.recent_sales')">
                @if ($recentSales->isEmpty())
                    <x-empty-state :message="__('dashboard.no_sales')" />
                @else
                    <x-table>
                        @slot('head')
                            <tr>
                                <x-table.th class="px-5">{{ __('dashboard.document_number') }}</x-table.th>
                                <x-table.th class="px-5">{{ __('general.created_at') }}</x-table.th>
                                <x-table.th align="right" class="px-5">{{ __('dashboard.total') }}</x-table.th>
                                <x-table.th class="px-5">{{ __('dashboard.status') }}</x-table.th>
                            </tr>
                        @endslot
                        @foreach ($recentSales as $sale)
                            @php
                                $statusColors = [
                                    'paid' => 'emerald',
                                    'partial' => 'amber',
                                    'pending' => 'sky',
                                    'approved' => 'indigo',
                                    'draft' => 'slate',
                                ];
                            @endphp
                            <tr class="transition hover:bg-slate-50">
                                <x-table.td class="px-5 font-medium text-slate-900">
                                    <a href="{{ route('sales.invoice_preview', $sale) }}" class="text-indigo-600 hover:text-indigo-800 hover:underline">
                                        {{ $sale->document_number }}
                                    </a>
                                </x-table.td>
                                <x-table.td class="px-5 text-slate-500">{{ $sale->invoice_date->format('Y-m-d') }}</x-table.td>
                                <x-table.td align="right" class="px-5 font-semibold text-slate-900">{{ number_format($sale->total_amount, 2) }}</x-table.td>
                                <x-table.td class="px-5">
                                    <x-badge :color="$statusColors[$sale->status->value] ?? 'slate'">
                                        {{ $sale->status->label() }}
                                    </x-badge>
                                </x-table.td>
                            </tr>
                        @endforeach
                    </x-table>
                @endif
            </x-card>

            <x-card :title="__('dashboard.low_stock')">
                @if ($lowStockProducts->isEmpty())
                    <x-empty-state :message="__('dashboard.no_low_stock')" />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($lowStockProducts as $product)
                            @php
                                $stock = (float) ($product->stock_in - $product->stock_out);
                            @endphp
                            <li class="flex items-center justify-between gap-4 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-900">{{ $product->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $product->code }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-sm font-semibold {{ $stock <= 0 ? 'text-rose-600' : 'text-amber-600' }}">
                                        {{ rtrim(rtrim(number_format($stock, 2), '0'), '.') ?: '0' }}
                                    </p>
                                    <p class="text-xs text-slate-500">{{ __('dashboard.min_stock') }}: {{ rtrim(rtrim(number_format($product->min_stock, 2), '0'), '.') ?: '0' }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
</x-layout>
