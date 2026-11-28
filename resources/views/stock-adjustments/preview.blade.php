<x-print-layout :title="__('stock_adjustments.print_preview')">
    @php
        $directionLabel = $adjustment->movement_type === \App\Enums\MovementType::IN
            ? __('stock_adjustments.movement_in')
            : __('stock_adjustments.movement_out');
        $overrideLabel = $adjustment->is_override
            ? __('stock_adjustments.override_yes')
            : __('stock_adjustments.override_no');
        $quantity = number_format((float) $adjustment->quantity, 3);
        $unitCost = number_format((float) $adjustment->unit_cost, 2);
        $balanceAfter = number_format((float) ($adjustment->movement?->balance_after ?? 0), 3);
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
                        {{ __('stock_adjustments.paper_a4') }}
                    </button>
                    <button
                        type="button"
                        @click="setSize('thermal')"
                        :class="size === 'thermal' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50'"
                        class="rounded-lg px-4 py-2 text-sm font-medium shadow-sm transition focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                    >
                        {{ __('stock_adjustments.paper_thermal') }}
                    </button>
                </div>
                <button
                    type="button"
                    onclick="window.print()"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring active:bg-indigo-500"
                >
                    {{ __('stock_adjustments.print') }}
                </button>
            </div>
        </div>

        <div class="py-8">
            <div x-show="size === 'a4'" class="print-sheet mx-auto max-w-[210mm] bg-white p-10 shadow-lg">
                <div class="flex flex-wrap items-start justify-between gap-6">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900">{{ $store['name'] }}</h1>
                        <p class="mt-1 text-sm text-slate-500">{{ $store['address'] ?: '—' }}</p>
                        <p class="text-sm text-slate-500">{{ $store['phone'] ?: '—' }}</p>
                    </div>
                    <div class="text-right">
                        <h2 class="text-2xl font-bold tracking-wide text-indigo-600 uppercase">{{ __('stock_adjustments.document_title') }}</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ $adjustment->document_number }}</p>
                        <x-badge color="{{ $adjustment->is_override ? 'rose' : 'indigo' }}" class="mt-2">
                            {{ $overrideLabel }}
                        </x-badge>
                    </div>
                </div>

                <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('stock_adjustments.product') }}</h3>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $adjustment->product?->name ?? '—' }}</p>
                        <p class="text-sm text-slate-500">{{ __('stock_adjustments.warehouse') }}: {{ $adjustment->warehouse?->name ?? '—' }}</p>
                    </div>
                    <div class="sm:text-right">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('stock_adjustments.details') }}</h3>
                        <p class="mt-2 text-sm text-slate-500">{{ __('stock_adjustments.date') }}: <span class="font-medium text-slate-900">{{ $adjustment->movement_date?->format('Y-m-d') ?? '—' }}</span></p>
                        <p class="text-sm text-slate-500">{{ __('stock_adjustments.direction') }}: <span class="font-medium text-slate-900">{{ $directionLabel }}</span></p>
                        <p class="text-sm text-slate-500">{{ __('stock_adjustments.reason') }}: <span class="font-medium text-slate-900">{{ $adjustment->reason?->label() ?? '—' }}</span></p>
                        <p class="text-sm text-slate-500">{{ __('stock_adjustments.created_by') }}: <span class="font-medium text-slate-900">{{ $adjustment->creator?->name ?? '—' }}</span></p>
                    </div>
                </div>

                <table class="mt-8 w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">{{ __('stock_adjustments.direction') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('stock_adjustments.quantity') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('stock_adjustments.unit_cost') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('stock_adjustments.balance_after') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $directionLabel }}</td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ $quantity }}</td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ $unitCost }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ $balanceAfter }}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-8">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('stock_adjustments.note') }}</h3>
                    <p class="mt-2 text-sm text-slate-500">{{ $adjustment->note ?: '—' }}</p>
                    <p class="mt-2 text-xs text-slate-500">{{ __('stock_adjustments.status') }}: {{ $overrideLabel }}</p>
                </div>

                <p class="mt-10 border-t border-slate-200 pt-4 text-center text-xs text-slate-400">
                    {{ $footer }}
                </p>
            </div>

            <div x-show="size === 'thermal'" x-cloak class="print-sheet mx-auto w-[80mm] bg-white p-4 font-mono text-[11px] leading-relaxed text-slate-900 shadow-lg">
                <div class="text-center">
                    <h2 class="text-sm font-bold uppercase">{{ $store['name'] }}</h2>
                    <p>{{ $store['address'] ?: '—' }}</p>
                    <p>{{ $store['phone'] ?: '—' }}</p>
                </div>

                <p class="my-2 border-t border-dashed border-slate-400"></p>

                <p>{{ __('stock_adjustments.document_title') }}: {{ $adjustment->document_number }}</p>
                <p>{{ __('stock_adjustments.date') }}: {{ $adjustment->movement_date?->format('Y-m-d') ?? '—' }}</p>
                <p>{{ __('stock_adjustments.product') }}: {{ $adjustment->product?->name ?? '—' }}</p>
                <p>{{ __('stock_adjustments.warehouse') }}: {{ $adjustment->warehouse?->name ?? '—' }}</p>
                <p>{{ __('stock_adjustments.direction') }}: {{ $directionLabel }}</p>
                <p>{{ __('stock_adjustments.reason') }}: {{ $adjustment->reason?->label() ?? '—' }}</p>
                <p>{{ __('stock_adjustments.created_by') }}: {{ $adjustment->creator?->name ?? '—' }}</p>

                <p class="my-2 border-t border-dashed border-slate-400"></p>

                <p>{{ __('stock_adjustments.quantity') }}: {{ $quantity }}</p>
                <p>{{ __('stock_adjustments.unit_cost') }}: {{ $unitCost }}</p>
                <p>{{ __('stock_adjustments.balance_after') }}: {{ $balanceAfter }}</p>
                <p>{{ __('stock_adjustments.status') }}: {{ $overrideLabel }}</p>

                <p class="my-2 border-t border-dashed border-slate-400"></p>

                <p class="text-center">{{ $adjustment->note ?: '—' }}</p>
                <p class="mt-1 text-center">{{ $footer }}</p>
            </div>
        </div>
    </div>
</x-print-layout>
