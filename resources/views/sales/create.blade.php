<x-layout>
    @php
        $sampleCustomers = ['Walk-in Customer', 'John Smith', 'Sara Johnson'];
        $sampleWarehouses = ['Main Warehouse', 'Downtown Store'];
        $sampleProducts = ['Espresso Coffee 250g', 'Green Tea 100g', 'Ceramic Mug', 'Honey Jar 500g'];
        $samplePaymentMethods = ['Cash', 'Card', 'Bank Transfer'];
    @endphp

    <div
        class="mx-auto max-w-7xl space-y-6"
        x-data="{
            items: [{ product: '', qty: 1, price: 0, discount: 0, tax: 0 }],
            paid: 0,
            addItem() { this.items.push({ product: '', qty: 1, price: 0, discount: 0, tax: 0 }) },
            removeItem(index) { this.items.splice(index, 1) },
            fmt(value) { return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
            lineBase(item) { return item.qty * item.price - item.discount },
            lineTotal(item) { return this.lineBase(item) + this.lineBase(item) * item.tax / 100 },
            get subtotal() { return this.items.reduce((sum, item) => sum + item.qty * item.price, 0) },
            get discount() { return this.items.reduce((sum, item) => sum + Number(item.discount || 0), 0) },
            get tax() { return this.items.reduce((sum, item) => sum + this.lineBase(item) * item.tax / 100, 0) },
            get grandTotal() { return this.subtotal - this.discount + this.tax },
            get due() { return this.grandTotal - this.paid },
        }"
    >
        <x-page-header :title="__('sales.title')" :subtitle="__('sales.subtitle')">
            <x-slot:actions>
                <x-button variant="secondary" :href="route('sales.invoice_preview')" wire:navigate>
                    {{ __('sales.preview_invoice') }}
                </x-button>
                <x-button :title="__('sales.sample_notice')">
                    {{ __('general.save') }}
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card :title="__('sales.details')">
            <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
                <x-form.select name="customer" :label="__('sales.customer')" :options="array_combine($sampleCustomers, $sampleCustomers)" />
                <x-form.select name="warehouse" :label="__('sales.warehouse')" :options="array_combine($sampleWarehouses, $sampleWarehouses)" />
                <x-form.input name="invoice_date" type="date" :label="__('sales.invoice_date')" :value="now()->format('Y-m-d')" />
                <x-form.input name="due_date" type="date" :label="__('sales.due_date')" :value="now()->addDays(14)->format('Y-m-d')" />
            </div>
        </x-card>

        <x-card :title="__('sales.items')">
            @slot('actions')
                <button
                    type="button"
                    @click="addItem"
                    class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-600 transition hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ __('sales.add_item') }}
                </button>
            @endslot

            <div class="hidden px-5 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500 lg:grid lg:grid-cols-12 lg:gap-3">
                <span class="col-span-4">{{ __('sales.product') }}</span>
                <span class="col-span-1">{{ __('sales.qty') }}</span>
                <span class="col-span-2">{{ __('sales.unit_price') }}</span>
                <span class="col-span-2">{{ __('sales.discount') }}</span>
                <span class="col-span-1">{{ __('sales.tax') }} %</span>
                <span class="col-span-1 text-right">{{ __('dashboard.total') }}</span>
                <span class="col-span-1"></span>
            </div>

            <div class="space-y-3 px-5 pb-5">
                <template x-for="(item, index) in items" :key="index">
                    <div class="grid grid-cols-2 items-end gap-3 rounded-lg border border-slate-100 p-3 sm:grid-cols-6 lg:grid-cols-12 lg:border-0 lg:p-0">
                        <div class="col-span-2 sm:col-span-6 lg:col-span-4">
                            <label class="block text-xs font-medium text-slate-500 lg:hidden" :for="'product-' + index">{{ __('sales.product') }}</label>
                            <select
                                :id="'product-' + index"
                                x-model="item.product"
                                class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                            >
                                <option value="">{{ __('sales.select_product') }}</option>
                                @foreach ($sampleProducts as $product)
                                    <option value="{{ $product }}">{{ $product }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 lg:hidden" :for="'qty-' + index">{{ __('sales.qty') }}</label>
                            <input type="number" min="0" step="0.001" :id="'qty-' + index" x-model.number="item.qty" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 lg:hidden" :for="'price-' + index">{{ __('sales.unit_price') }}</label>
                            <input type="number" min="0" step="0.01" :id="'price-' + index" x-model.number="item.price" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 lg:hidden" :for="'discount-' + index">{{ __('sales.discount') }}</label>
                            <input type="number" min="0" step="0.01" :id="'discount-' + index" x-model.number="item.discount" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 lg:hidden" :for="'tax-' + index">{{ __('sales.tax') }} %</label>
                            <input type="number" min="0" step="0.01" :id="'tax-' + index" x-model.number="item.tax" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30" />
                        </div>
                        <div class="text-sm font-semibold text-slate-900 sm:text-right" x-text="fmt(lineTotal(item))"></div>
                        <div class="sm:text-right">
                            <button
                                type="button"
                                @click="removeItem(index)"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-rose-500 transition hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus:ring-2 focus:ring-rose-500/30"
                                :aria-label="__('general.delete')"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </x-card>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <x-card :title="__('dashboard.notes')" class="lg:col-span-2">
                <div class="p-5">
                    <x-form.textarea name="notes" :label="__('sales.notes')" :help="__('sales.notes_help')" />
                </div>
            </x-card>

            <x-card :title="__('sales.summary')">
                <div class="space-y-2 p-5 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">{{ __('sales.subtotal') }}</span><span class="font-medium text-slate-900" x-text="fmt(subtotal)"></span></div>
                    <div class="flex justify-between"><span class="text-slate-500">{{ __('sales.discount') }}</span><span class="font-medium text-slate-900" x-text="'-' + fmt(discount)"></span></div>
                    <div class="flex justify-between"><span class="text-slate-500">{{ __('sales.tax') }}</span><span class="font-medium text-slate-900" x-text="fmt(tax)"></span></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 text-base"><span class="font-semibold text-slate-900">{{ __('sales.grand_total') }}</span><span class="font-bold text-slate-900" x-text="fmt(grandTotal)"></span></div>
                </div>
                <div class="space-y-4 border-t border-slate-200 p-5 pt-4">
                    <x-form.select name="payment_method" :label="__('sales.payment_method')" :options="array_combine($samplePaymentMethods, $samplePaymentMethods)" />
                    <div>
                        <label for="paid" class="block text-sm font-medium text-slate-700">{{ __('sales.paid_amount') }}</label>
                        <input type="number" min="0" step="0.01" name="paid" id="paid" x-model.number="paid" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30" />
                    </div>
                    <div class="flex justify-between rounded-lg bg-rose-50 px-3 py-2 text-sm">
                        <span class="font-medium text-rose-700">{{ __('sales.due_amount') }}</span>
                        <span class="font-bold text-rose-700" x-text="fmt(due)"></span>
                    </div>
                </div>
            </x-card>
        </div>
    </div>
</x-layout>
