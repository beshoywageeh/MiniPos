<?php

use App\Enums\DocumentStatus;
use App\Enums\MovementType;
use App\Enums\PartyType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\ProductType;
use App\Models\Cashbox;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Warehouse;
use App\Support\DocumentNumber;
use App\Support\ProductMatcher;
use App\Support\StockMovementRecorder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public int|string $partyId = '';

    public int|string $warehouseId = '';

    public string $invoiceDate = '';

    public string $dueDate = '';

    public string $notes = '';

    public bool $isDraft = false;

    public string $paymentMethod = '';

    public int|string $cashboxId = '';

    public string $paidAmount = '';

    /** @var list<array<string, mixed>> */
    public array $items = [];

    public string $searchTerm = '';

    /** @var list<array<string, mixed>> */
    public array $searchResults = [];

    public function mount(): void
    {
        $this->invoiceDate = today()->format('Y-m-d');
        $this->dueDate = today()->addDays(14)->format('Y-m-d');
        $this->paymentMethod = PaymentMethod::CASH->value;

        $warehouses = $this->warehouseOptions();
        if (count($warehouses) === 1) {
            $this->warehouseId = (int) array_key_first($warehouses);
        }

        $cashboxes = $this->cashboxOptions();
        if (count($cashboxes) === 1) {
            $this->cashboxId = (int) array_key_first($cashboxes);
        }

        $this->addItem();
    }

    public function addItem(): void
    {
        $this->items[] = [
            'product_id' => null,
            'product_name' => '',
            'quantity' => '1',
            'unit_price' => '',
            'discount_amount' => '',
            'tax_rate' => '',
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function updatedSearchTerm(): void
    {
        $this->searchResults = collect(ProductMatcher::search($this->searchTerm))
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'sku' => $product->sku,
                'purchase_price' => (float) $product->purchase_price,
            ])
            ->all();
    }

    public function addFirstSearchResult(string $term): void
    {
        $this->searchTerm = $term;
        $this->updatedSearchTerm();

        if ($this->searchResults === []) {
            return;
        }

        $this->addProductFromSearch((int) $this->searchResults[0]['id']);
    }

    public function addProductFromSearch(int $productId): void
    {
        $product = Product::query()
            ->whereKey($productId)
            ->where('is_active', true)
            ->first();

        if ($product === null) {
            $this->searchTerm = '';
            $this->searchResults = [];

            return;
        }

        $existingIndex = collect($this->items)->search(
            fn (array $item): bool => isset($item['product_id']) && (int) $item['product_id'] === $product->id
        );

        if ($existingIndex !== false) {
            $this->items[$existingIndex]['quantity'] = rtrim(rtrim(bcadd((string) $this->items[$existingIndex]['quantity'], '1', 3), '0'), '.');
        } else {
            $blankIndex = collect($this->items)->search(fn (array $item): bool => $item['product_id'] === null);

            if ($blankIndex === false) {
                $this->addItem();
                $blankIndex = count($this->items) - 1;
            }

            $this->items[$blankIndex]['product_id'] = $product->id;
            $this->items[$blankIndex]['product_name'] = $product->name;
            $this->items[$blankIndex]['quantity'] = '1';
            $this->items[$blankIndex]['unit_price'] = (string) $product->purchase_price;
        }

        $this->searchTerm = '';
        $this->searchResults = [];
    }

    /**
     * @return array<int|string, string>
     */
    #[Computed]
    public function supplierOptions(): array
    {
        return ['' => __('purchases.select_supplier')]
            + Party::query()
                ->where('is_active', true)
                ->whereIn('type', [PartyType::SUPPLIER, PartyType::BOTH])
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
    }

    /**
     * @return array<int|string, string>
     */
    #[Computed]
    public function warehouseOptions(): array
    {
        $options = Warehouse::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();

        return count($options) > 1 ? ['' => __('purchases.select_warehouse')] + $options : $options;
    }

    /**
     * @return array<int|string, string>
     */
    #[Computed]
    public function cashboxOptions(): array
    {
        $options = Cashbox::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();

        return count($options) > 1 ? ['' => __('purchases.select_cashbox')] + $options : $options;
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function paymentMethodOptions(): array
    {
        return collect(PaymentMethod::cases())
            ->mapWithKeys(fn (PaymentMethod $method): array => [$method->value => $method->label()])
            ->all();
    }

    #[Computed]
    public function subtotal(): float
    {
        return $this->lineSubtotalSum();
    }

    #[Computed]
    public function discountTotal(): float
    {
        return round($this->collectItems(fn (array $item): float => (float) ($item['discount_amount'] ?: 0)), 2);
    }

    #[Computed]
    public function taxTotal(): float
    {
        return round($this->collectItems(function (array $item): float {
            $base = $this->lineBase($item);

            return $base * (float) ($item['tax_rate'] ?: 0) / 100;
        }), 2);
    }

    #[Computed]
    public function grandTotal(): float
    {
        return round($this->subtotal - $this->discountTotal + $this->taxTotal, 2);
    }

    #[Computed]
    public function dueAmount(): float
    {
        return round($this->grandTotal - $this->paidFloat(), 2);
    }

    public function save(): void
    {
        $this->validate();

        $total = $this->grandTotal;
        $paid = $this->isDraft ? 0.0 : $this->paidFloat();

        if (! $this->isDraft && $paid > $total) {
            $this->addError('paidAmount', __('purchases.paid_exceeds_total'));

            return;
        }

        $purchase = DB::transaction(function () use ($paid, $total): Purchase {
            $products = Product::query()
                ->whereIn('id', collect($this->items)->pluck('product_id'))
                ->get()
                ->keyBy('id');

            $purchase = Purchase::query()->create([
                'document_number' => DocumentNumber::next('purchase'),
                'party_id' => $this->partyId,
                'warehouse_id' => $this->warehouseId,
                'invoice_date' => $this->invoiceDate,
                'due_date' => $this->dueDate !== '' ? $this->dueDate : null,
                'subtotal' => $this->subtotal,
                'discount_amount' => $this->discountTotal,
                'tax_amount' => $this->taxTotal,
                'total_amount' => $total,
                'paid_amount' => $paid,
                'remaining_amount' => round($total - $paid, 2),
                'status' => $this->deriveStatus($paid, $total),
                'notes' => $this->notes,
                'created_by' => Auth::id(),
            ]);

            $purchase->items()->createMany($this->buildPurchaseItems($products));

            if ($this->isDraft) {
                return $purchase;
            }

            $warehouse = Warehouse::query()->findOrFail($purchase->warehouse_id);
            $recorder = app(StockMovementRecorder::class);

            foreach ($purchase->items as $item) {
                if ($products[$item->product_id]->type === ProductType::Service) {
                    continue;
                }

                $recorder->record([
                    'product' => $products[$item->product_id],
                    'warehouse' => $warehouse,
                    'movement_type' => MovementType::IN,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_price,
                    'reference_type' => Purchase::class,
                    'reference_id' => $purchase->id,
                    'note' => null,
                    'movement_date' => $purchase->invoice_date,
                    'created_by' => Auth::id(),
                ]);
            }

            if ($paid > 0) {
                Payment::query()->create([
                    'document_number' => DocumentNumber::next('payment'),
                    'party_id' => $purchase->party_id,
                    'cashbox_id' => $this->cashboxId,
                    'purchase_id' => $purchase->id,
                    'type' => PaymentType::payment,
                    'method' => $this->paymentMethod,
                    'amount' => $paid,
                    'payment_date' => $purchase->invoice_date,
                    'created_by' => Auth::id(),
                ]);
            }

            return $purchase;
        });

        $this->redirectRoute('purchases.invoice_preview', ['purchase' => $purchase], navigate: true);
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        $requiresPaymentDetails = ! $this->isDraft && $this->paidFloat() > 0;

        return [
            'partyId' => ['required', 'integer', Rule::exists('parties', 'id')->where(fn ($query) => $query
                ->where('is_active', true)
                ->whereIn('type', [PartyType::SUPPLIER->value, PartyType::BOTH->value]))],
            'warehouseId' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'invoiceDate' => ['required', 'date'],
            'dueDate' => ['nullable', 'date', 'after_or_equal:invoiceDate'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'isDraft' => ['boolean'],
            'paymentMethod' => [Rule::requiredIf($requiresPaymentDetails), Rule::in(array_column(PaymentMethod::cases(), 'value'))],
            'cashboxId' => [Rule::requiredIf($requiresPaymentDetails), 'integer', Rule::exists('cashboxes', 'id')->where('is_active', true)],
            'paidAmount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    private function deriveStatus(float $paid, float $total): DocumentStatus
    {
        if ($this->isDraft) {
            return DocumentStatus::DRAFT;
        }

        return match (true) {
            $paid >= $total => DocumentStatus::PAID,
            $paid > 0 => DocumentStatus::PARTIAL,
            default => DocumentStatus::PENDING,
        };
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    private function buildPurchaseItems($products): array
    {
        return collect($this->items)
            ->map(fn (array $item): array => [
                'product_id' => $item['product_id'],
                'product_name' => $products[$item['product_id']]->name,
                'sku' => $products[$item['product_id']]->sku,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount_amount' => $item['discount_amount'] !== '' ? $item['discount_amount'] : 0,
                'tax_amount' => round($this->lineBase($item) * (float) ($item['tax_rate'] ?: 0) / 100, 2),
                'line_total' => round($this->lineBase($item) + $this->lineBase($item) * (float) ($item['tax_rate'] ?: 0) / 100, 2),
            ])
            ->all();
    }

    /**
     * @param  callable(array<string, mixed>): float  $callback
     */
    private function collectItems($callback): float
    {
        return collect($this->items)->sum($callback);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function lineBase(array $item): float
    {
        return round((float) $item['quantity'] * (float) $item['unit_price'] - (float) ($item['discount_amount'] ?: 0), 2);
    }

    private function lineSubtotalSum(): float
    {
        return round($this->collectItems(fn (array $item): float => (float) $item['quantity'] * (float) $item['unit_price']), 2);
    }

    private function paidFloat(): float
    {
        return (float) ($this->paidAmount === '' ? 0 : $this->paidAmount);
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6">
    <form wire:submit="save">
        <x-page-header :title="__('purchases.title')" :subtitle="__('purchases.subtitle')">
            <x-slot:actions>
                <x-button type="submit" wire:loading.attr="disabled">
                    {{ __('purchases.save') }}
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card :title="__('purchases.details')">
            <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
                <x-form.select name="partyId" wire:model="partyId" :label="__('purchases.supplier')"
                    :options="$this->supplierOptions" :selected="$partyId" />
                <x-form.select name="warehouseId" wire:model="warehouseId" :label="__('purchases.warehouse')"
                    :options="$this->warehouseOptions" :selected="$warehouseId" />
                <x-form.input type="date" name="invoiceDate" wire:model="invoiceDate" :value="$invoiceDate"
                    :label="__('purchases.invoice_date')" />
                <x-form.input type="date" name="dueDate" wire:model="dueDate" :value="$dueDate"
                    :label="__('purchases.due_date')" />
                <div class="flex items-end pb-2">
                    <x-form.checkbox name="isDraft" wire:model.live="isDraft" :label="__('purchases.draft')"
                        :checked="$isDraft" />
                </div>
            </div>
        </x-card>

        <x-card :title="__('purchases.items')">
            @slot('actions')
                <button
                    type="button"
                    wire:click="addItem"
                    class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-600 transition hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="h-4 w-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ __('purchases.add_item') }}
                </button>
            @endslot

            <div class="px-5 pb-3 pt-4">
                <div class="relative">
                    <x-form.input type="text" name="searchTerm" wire:model.live.debounce.400ms="searchTerm"
                        wire:keydown.enter.prevent="addFirstSearchResult($event.target.value)" autocomplete="off"
                        placeholder="{{ __('purchases.search_product') }}" :label="__('purchases.product')" />
                    @if (count($this->searchResults) > 0)
                        <div class="absolute inset-x-0 z-10 mt-1 max-h-60 overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                            @foreach ($this->searchResults as $result)
                                <button type="button" wire:click.prevent="addProductFromSearch({{ $result['id'] }})"
                                    class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm transition hover:bg-indigo-50"
                                    wire:key="purchase-search-result-{{ $result['id'] }}"
                                >
                                    <span class="min-w-0">
                                        <span class="block truncate font-medium text-slate-900">{{ $result['name'] }}</span>
                                        <span class="block truncate text-xs text-slate-500">{{ $result['code'] }}{{ $result['sku'] ? ' · '.$result['sku'] : '' }}</span>
                                    </span>
                                    <span class="shrink-0 text-xs font-semibold text-slate-700">{{ number_format($result['purchase_price'], 2) }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="hidden px-5 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500 lg:grid lg:grid-cols-12 lg:gap-3">
                <span class="col-span-4">{{ __('purchases.product') }}</span>
                <span class="col-span-1">{{ __('purchases.qty') }}</span>
                <span class="col-span-2">{{ __('purchases.unit_price') }}</span>
                <span class="col-span-2">{{ __('purchases.discount') }}</span>
                <span class="col-span-1">{{ __('purchases.tax').' %' }}</span>
                <span class="col-span-1 text-right">{{ __('purchases.total') }}</span>
                <span class="col-span-1"></span>
            </div>

            <div class="space-y-3 px-5 pb-5">
                @foreach ($items as $index => $item)
                    <div class="grid grid-cols-2 items-end gap-3 rounded-lg border border-slate-100 p-3 sm:grid-cols-6 lg:grid-cols-12 lg:border-0 lg:p-0"
                        wire:key="purchase-item-{{ $index }}">
                        <div class="col-span-2 sm:col-span-6 lg:col-span-4">
                            <span class="block text-xs font-medium text-slate-500 lg:hidden">{{ __('purchases.product') }}</span>
                            @if ($item['product_id'])
                                <div class="mt-1 flex items-center gap-2 rounded-lg bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                        stroke="currentColor" class="h-4 w-4 shrink-0">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    <span class="truncate">{{ $item['product_name'] }}</span>
                                </div>
                            @else
                                <div class="mt-1 rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-400">
                                    {{ __('purchases.search_product') }}
                                </div>
                            @endif
                        </div>
                        <div>
                            <x-form.input type="number" min="0" step="0.001" name="items.{{ $index }}.quantity"
                                wire:model.live.debounce.300ms="items.{{ $index }}.quantity" :label="__('purchases.qty')" />
                        </div>
                        <div>
                            <x-form.input type="number" min="0" step="0.01" name="items.{{ $index }}.unit_price"
                                wire:model.live.debounce.300ms="items.{{ $index }}.unit_price" :label="__('purchases.unit_price')" />
                        </div>
                        <div>
                            <x-form.input type="number" min="0" step="0.01" name="items.{{ $index }}.discount_amount"
                                wire:model.live.debounce.300ms="items.{{ $index }}.discount_amount" :label="__('purchases.discount')" />
                        </div>
                        <div>
                            <x-form.input type="number" min="0" step="0.01" name="items.{{ $index }}.tax_rate"
                                wire:model.live.debounce.300ms="items.{{ $index }}.tax_rate" :label="__('purchases.tax').' %'" />
                        </div>
                        @php
                            $lineBase = (float) $item['quantity'] * (float) $item['unit_price'] - (float) ($item['discount_amount'] ?: 0);
                            $lineTotal = $lineBase + $lineBase * (float) ($item['tax_rate'] ?: 0) / 100;
                        @endphp
                        <div class="text-sm font-semibold text-slate-900 sm:text-right">
                            {{ number_format($lineTotal, 2) }}
                        </div>
                        <div class="sm:text-right">
                            <button
                                type="button"
                                wire:click="removeItem({{ $index }})"
                                wire:loading.attr="disabled"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-rose-500 transition hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus:ring-2 focus:ring-rose-500/30"
                                aria-label="{{ __('purchases.remove_item') }}"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                    stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <x-card :title="__('purchases.notes')" class="lg:col-span-2">
                <div class="p-5">
                    <x-form.textarea name="notes" wire:model="notes" :label="__('purchases.notes')"
                        :help="__('purchases.notes_help')" />
                </div>
            </x-card>

            <x-card :title="__('purchases.summary')">
                <div class="space-y-2 p-5 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">{{ __('purchases.subtotal') }}</span><span
                            class="font-medium text-slate-900">{{ number_format($this->subtotal, 2) }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">{{ __('purchases.discount') }}</span><span
                            class="font-medium text-slate-900">-{{ number_format($this->discountTotal, 2) }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">{{ __('purchases.tax') }}</span><span
                            class="font-medium text-slate-900">{{ number_format($this->taxTotal, 2) }}</span></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 text-base"><span
                            class="font-semibold text-slate-900">{{ __('purchases.grand_total') }}</span><span
                            class="font-bold text-slate-900">{{ number_format($this->grandTotal, 2) }}</span></div>
                </div>
                @unless ($isDraft)
                    <div class="space-y-4 border-t border-slate-200 p-5 pt-4">
                        <x-form.select name="paymentMethod" wire:model="paymentMethod"
                            :label="__('purchases.payment_method')" :options="$this->paymentMethodOptions"
                            :selected="$paymentMethod" />
                        <x-form.select name="cashboxId" wire:model="cashboxId" :label="__('purchases.cashbox')"
                            :options="$this->cashboxOptions" :selected="$cashboxId" />
                        <x-form.input type="number" min="0" step="0.01" name="paidAmount"
                            wire:model.live.debounce.300ms="paidAmount" :label="__('purchases.paid_amount')" />
                        <div class="flex justify-between rounded-lg bg-rose-50 px-3 py-2 text-sm">
                            <span class="font-medium text-rose-700">{{ __('purchases.due_amount') }}</span>
                            <span class="font-bold text-rose-700">{{ number_format($this->dueAmount, 2) }}</span>
                        </div>
                    </div>
                @endunless
            </x-card>
        </div>
    </form>
</div>
