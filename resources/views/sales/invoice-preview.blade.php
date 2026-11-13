<x-print-layout :title="__('sales.print_preview')">
    @php
        $sample = [
            'number' => 'INV-000001',
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(14)->format('Y-m-d'),
            'status' => 'PARTIAL',
            'store' => [
                'name' => config('app.name', 'MiniPOS'),
                'address' => '12 Main Street, Downtown',
                'phone' => '+1 (555) 123-4567',
            ],
            'customer' => [
                'name' => 'John Smith',
                'phone' => '+1 (555) 987-6543',
                'address' => '88 Elm Road, Riverside',
            ],
            'cashier' => 'Alex Doe',
            'items' => [
                ['name' => 'Espresso Coffee 250g', 'sku' => 'SKU-00123', 'qty' => 2, 'price' => 8.50, 'discount' => 0, 'tax' => 10],
                ['name' => 'Green Tea 100g', 'sku' => 'SKU-00456', 'qty' => 1, 'price' => 5.20, 'discount' => 1.00, 'tax' => 10],
                ['name' => 'Ceramic Mug', 'sku' => 'SKU-00789', 'qty' => 3, 'price' => 4.00, 'discount' => 0, 'tax' => 0],
            ],
            'paid' => 10.00,
            'payment_method' => 'Cash',
            'notes' => 'Thank you for your business.',
        ];
        $sampleSubtotal = collect($sample['items'])->sum(fn ($item) => $item['qty'] * $item['price']);
        $sampleDiscount = collect($sample['items'])->sum('discount');
        $sampleTax = collect($sample['items'])->sum(fn ($item) => ($item['qty'] * $item['price'] - $item['discount']) * $item['tax'] / 100);
        $sampleTotal = $sampleSubtotal - $sampleDiscount + $sampleTax;
        $sampleDue = $sampleTotal - $sample['paid'];
    @endphp

    <div
        x-data="{
            size: 'a4',
            setSize(value) {
                this.size = value;
                $refs.pageSize.textContent = value === 'a4'
                    ? '@page { size: A4; margin: 12mm; }'
                    : '@page { size: 80mm auto; margin: 4mm; }';
            },
        }"
        x-init="setSize('a4')"
    >
        <style x-ref="pageSize"></style>

        <div class="no-print border-b border-slate-300 bg-white px-4 py-3">
            <div class="mx-auto flex max-w-4xl items-center justify-between gap-3">
                <div class="flex gap-2">
                    <button
                        type="button"
                        @click="setSize('a4')"
                        :class="size === 'a4' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50'"
                        class="rounded-lg px-4 py-2 text-sm font-medium shadow-sm transition focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                    >
                        {{ __('sales.paper_a4') }}
                    </button>
                    <button
                        type="button"
                        @click="setSize('thermal')"
                        :class="size === 'thermal' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50'"
                        class="rounded-lg px-4 py-2 text-sm font-medium shadow-sm transition focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                    >
                        {{ __('sales.paper_thermal') }}
                    </button>
                </div>
                <button
                    type="button"
                    onclick="window.print()"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring active:bg-indigo-500"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.794-2.005-1.853-2.153a48.387 48.387 0 0 0-1.92-.213m-8.457 0a48.655 48.655 0 0 1 1.919-.214C11.05 6.716 12.838 6.75 14 6.75c1.163 0 2.95.033 4.228.183M3.75 8.25a2.25 2.25 0 0 1 2.25-2.25h.375m12.553 2.153c-.132-.874-.75-1.59-1.603-1.762A48.32 48.32 0 0 0 14 6.75M3.75 8.25V15a2.25 2.25 0 0 0 2.25 2.25h.75m-3-9.001 3-.001m9 4.5s-.001.001-.002.002l-.002.002a.75.75 0 0 1-1.061 0l-3.75-3.75a.75.75 0 0 1 0-1.061l.002-.002 3.75-3.75a.75.75 0 0 1 1.061 0l3.75 3.75a.75.75 0 0 1 0 1.061Zm-7.5-3.75a.75.75 0 0 1 0 1.061l-3.75 3.75a.75.75 0 0 1-1.061 0l-3.75-3.75a.75.75 0 0 1 0-1.061l3.75-3.75a.75.75 0 0 1 1.061 0Z" />
                    </svg>
                    {{ __('general.print') }}
                </button>
            </div>
        </div>

        <div class="py-8">
            {{-- A4 invoice --}}
            <div x-show="size === 'a4'" class="print-sheet mx-auto max-w-[210mm] bg-white p-10 shadow-lg">
                <div class="flex flex-wrap items-start justify-between gap-6">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900">{{ $sample['store']['name'] }}</h1>
                        <p class="mt-1 text-sm text-slate-500">{{ $sample['store']['address'] }}</p>
                        <p class="text-sm text-slate-500">{{ $sample['store']['phone'] }}</p>
                    </div>
                    <div class="text-right">
                        <h2 class="text-2xl font-bold tracking-wide text-indigo-600 uppercase">{{ __('sales.invoice') }}</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ $sample['number'] }}</p>
                        <x-badge color="amber" class="mt-2">
                            {{ __('DocumentStatus.'.$sample['status']) }}
                        </x-badge>
                    </div>
                </div>

                <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('sales.bill_to') }}</h3>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $sample['customer']['name'] }}</p>
                        <p class="text-sm text-slate-500">{{ $sample['customer']['phone'] }}</p>
                        <p class="text-sm text-slate-500">{{ $sample['customer']['address'] }}</p>
                    </div>
                    <div class="sm:text-right">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('sales.details') }}</h3>
                        <p class="mt-2 text-sm text-slate-500">{{ __('dashboard.recent_sales') }}: <span class="font-medium text-slate-900">{{ $sample['invoice_date'] }}</span></p>
                        <p class="text-sm text-slate-500">{{ __('sales.due_date') }}: <span class="font-medium text-slate-900">{{ $sample['due_date'] }}</span></p>
                        <p class="text-sm text-slate-500">{{ __('sales.cashier') }}: <span class="font-medium text-slate-900">{{ $sample['cashier'] }}</span></p>
                    </div>
                </div>

                <table class="mt-8 w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">#</th>
                            <th scope="col" class="px-4 py-3">{{ __('sales.item') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('sales.qty') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('sales.unit_price') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('sales.discount') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('sales.tax') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('dashboard.total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($sample['items'] as $index => $item)
                            @php $lineBase = $item['qty'] * $item['price'] - $item['discount']; @endphp
                            <tr>
                                <td class="px-4 py-3 text-slate-500">{{ $index + 1 }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-900">{{ $item['name'] }}</p>
                                    <p class="text-xs text-slate-400">{{ $item['sku'] }}</p>
                                </td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ rtrim(rtrim(number_format($item['qty'], 2), '0'), '.') ?: '0' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ number_format($item['price'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ number_format($item['discount'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ number_format(($item['qty'] * $item['price'] - $item['discount']) * $item['tax'] / 100, 2) }} ({{ rtrim(rtrim(number_format($item['tax'], 2), '0'), '.') ?: '0' }}%)</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ number_format($lineBase + $lineBase * $item['tax'] / 100, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="mt-8 flex flex-wrap items-start justify-between gap-6">
                    <div class="min-w-[200px] flex-1">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('sales.notes') }}</h3>
                        <p class="mt-2 text-sm text-slate-500">{{ $sample['notes'] }}</p>
                        <p class="mt-10 border-t border-slate-200 pt-2 text-xs text-slate-400">{{ __('sales.received_by') }}</p>
                    </div>
                    <div class="w-full max-w-xs space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-slate-500">{{ __('sales.subtotal') }}</span><span class="font-medium text-slate-900">{{ number_format($sampleSubtotal, 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">{{ __('sales.discount') }}</span><span class="font-medium text-slate-900">-{{ number_format($sampleDiscount, 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">{{ __('sales.tax') }}</span><span class="font-medium text-slate-900">{{ number_format($sampleTax, 2) }}</span></div>
                        <div class="flex justify-between border-t border-slate-200 pt-2 text-base"><span class="font-semibold text-slate-900">{{ __('sales.grand_total') }}</span><span class="font-bold text-slate-900">{{ number_format($sampleTotal, 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">{{ __('sales.paid_amount') }} ({{ $sample['payment_method'] }})</span><span class="font-medium text-emerald-600">{{ number_format($sample['paid'], 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">{{ __('sales.due_amount') }}</span><span class="font-semibold text-rose-600">{{ number_format($sampleDue, 2) }}</span></div>
                    </div>
                </div>

                <p class="mt-10 border-t border-slate-200 pt-4 text-center text-xs text-slate-400">
                    {{ __('sales.thank_you') }}
                </p>
            </div>

            {{-- 80mm thermal receipt --}}
            <div x-show="size === 'thermal'" x-cloak class="print-sheet mx-auto w-[80mm] bg-white p-4 font-mono text-[11px] leading-relaxed text-slate-900 shadow-lg">
                <div class="text-center">
                    <h2 class="text-sm font-bold uppercase">{{ $sample['store']['name'] }}</h2>
                    <p>{{ $sample['store']['address'] }}</p>
                    <p>{{ $sample['store']['phone'] }}</p>
                </div>

                <p class="my-2 border-t border-dashed border-slate-400"></p>

                <p>{{ __('sales.invoice_number') }}: {{ $sample['number'] }}</p>
                <p>{{ __('dashboard.recent_sales') }}: {{ $sample['invoice_date'] }}</p>
                <p>{{ __('sales.cashier') }}: {{ $sample['cashier'] }}</p>
                <p>{{ __('sales.bill_to') }}: {{ $sample['customer']['name'] }}</p>

                <p class="my-2 border-t border-dashed border-slate-400"></p>

                @foreach ($sample['items'] as $item)
                    @php
                        $lineBase = $item['qty'] * $item['price'] - $item['discount'];
                        $lineTotal = $lineBase + $lineBase * $item['tax'] / 100;
                    @endphp
                    <p class="font-semibold">{{ $item['name'] }}</p>
                    <div class="flex justify-between">
                        <span>{{ rtrim(rtrim(number_format($item['qty'], 2), '0'), '.') ?: '0' }} x {{ number_format($item['price'], 2) }}</span>
                        <span>{{ number_format($lineTotal, 2) }}</span>
                    </div>
                    @if ($item['discount'] > 0)
                        <div class="flex justify-between text-slate-500"><span>{{ __('sales.discount') }}</span><span>-{{ number_format($item['discount'], 2) }}</span></div>
                    @endif
                @endforeach

                <p class="my-2 border-t border-dashed border-slate-400"></p>

                <div class="flex justify-between"><span>{{ __('sales.subtotal') }}</span><span>{{ number_format($sampleSubtotal, 2) }}</span></div>
                <div class="flex justify-between"><span>{{ __('sales.discount') }}</span><span>-{{ number_format($sampleDiscount, 2) }}</span></div>
                <div class="flex justify-between"><span>{{ __('sales.tax') }}</span><span>{{ number_format($sampleTax, 2) }}</span></div>
                <div class="flex justify-between text-sm font-bold"><span>{{ __('sales.grand_total') }}</span><span>{{ number_format($sampleTotal, 2) }}</span></div>
                <div class="flex justify-between"><span>{{ __('sales.paid_amount') }} ({{ $sample['payment_method'] }})</span><span>{{ number_format($sample['paid'], 2) }}</span></div>
                <div class="flex justify-between"><span>{{ __('sales.due_amount') }}</span><span>{{ number_format($sampleDue, 2) }}</span></div>

                <p class="my-2 border-t border-dashed border-slate-400"></p>

                <p class="text-center">{{ $sample['notes'] }}</p>
                <p class="mt-1 text-center">{{ __('sales.thank_you') }}</p>
            </div>
        </div>
    </div>
</x-print-layout>
