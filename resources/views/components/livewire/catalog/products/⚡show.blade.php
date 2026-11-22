<?php

use App\Enums\MovementType;
use App\Enums\ProductType;
use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public Product $product;

    public function mount(Product $product): void
    {
        $this->product = $product;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object{warehouse_id: int, warehouse_name: string, stock: float}>
     */
    #[Computed]
    public function warehouseStock()
    {
        return $this->product->stockMovements()
            ->select('warehouse_id')
            ->selectRaw(
                "sum(case when movement_type = '".MovementType::IN->value."' then quantity else -quantity end) as stock",
            )
            ->with('warehouse')
            ->groupBy('warehouse_id')
            ->get()
            ->map(fn ($row): object => (object) [
                'warehouse_id' => (int) $row->warehouse_id,
                'warehouse_name' => $row->warehouse?->name ?? '—',
                'stock' => (float) $row->stock,
            ]);
    }

    /**
     * @return float
     */
    #[Computed]
    public function totalStock(): float
    {
        return (float) $this->warehouseStock()->sum('stock');
    }

    #[Computed]
    public function movements()
    {
        return $this->product->stockMovements()
            ->with('warehouse')
            ->latest('movement_date')
            ->latest('id')
            ->paginate(10);
    }
}
?>

<div class="mx-auto max-w-7xl space-y-6"
    x-data="{ flash: false, flashMessage: '' }"
    @product-adjusted.window="flashMessage = @js(__('products.adjusted')); flash = true; setTimeout(() => flash = false, 3000)">
    <div x-show="flash" x-cloak x-transition.opacity
        class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
        <span x-text="flashMessage"></span>
    </div>

    <div class="flex items-center gap-2 text-sm text-slate-500">
        <a href="{{ route('products.index') }}" class="inline-flex items-center gap-1 hover:text-slate-700">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            {{ __('products.back') }}
        </a>
    </div>

    <x-page-header :title="$product->name" :subtitle="$product->code">
        <x-slot:actions>
            <x-badge :color="$product->is_active ? 'emerald' : 'slate'">
                {{ $product->is_active ? __('products.active') : __('products.inactive') }}
            </x-badge>
            <livewire:livewire.catalog.products.actions.adjust :product="$product" />
            <livewire:livewire.catalog.products.actions.update :product="$product" />
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card title="{{ __('products.details') }}" class="lg:col-span-1">
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('products.type') }}</dt>
                    <dd class="font-medium text-slate-900">{{ $product->type->label() }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('products.category') }}</dt>
                    <dd class="font-medium text-slate-900">{{ $product->category?->name ?? '—' }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('products.brand') }}</dt>
                    <dd class="font-medium text-slate-900">{{ $product->brand?->name ?? '—' }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('products.unit') }}</dt>
                    <dd class="font-medium text-slate-900">{{ $product->unit?->name ?? '—' }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('products.sku') }}</dt>
                    <dd class="font-medium text-slate-900">{{ $product->sku ?? '—' }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('products.barcode') }}</dt>
                    <dd class="font-medium text-slate-900">{{ $product->barcode ?? '—' }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('products.purchase_price') }}</dt>
                    <dd class="font-medium text-slate-900">{{ number_format((float) $product->purchase_price, 2) }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('products.selling_price') }}</dt>
                    <dd class="font-medium text-slate-900">{{ number_format((float) $product->selling_price, 2) }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-slate-500">{{ __('products.min_stock') }}</dt>
                    <dd class="font-medium text-slate-900">{{ number_format((float) $product->min_stock, 3) }}</dd>
                </div>
            </dl>
            @if ($product->description)
                <p class="border-t border-slate-100 px-5 py-4 text-sm text-slate-600">{{ $product->description }}</p>
            @endif
        </x-card>

        <x-card title="{{ __('products.warehouse_stock') }}" class="lg:col-span-2">
            @if ($product->type === ProductType::Product)
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <span class="text-sm text-slate-500">{{ __('products.total_stock') }}</span>
                    <span class="text-lg font-semibold text-slate-900">
                        {{ number_format($this->totalStock, 3) }}
                        @if ($this->totalStock <= (float) $product->min_stock)
                            <x-badge color="rose">{{ __('products.low_stock') }}</x-badge>
                        @endif
                    </span>
                </div>
                <x-table>
                    @slot('head')
                        <tr>
                            <x-table.th>{{ __('products.warehouse') }}</x-table.th>
                            <x-table.th align="right">{{ __('products.stock') }}</x-table.th>
                        </tr>
                    @endslot
                    @forelse ($this->warehouseStock as $row)
                        <tr class="transition hover:bg-slate-50" wire:key="warehouse-stock-{{ $row->warehouse_id }}">
                            <x-table.td class="font-medium text-slate-900">{{ $row->warehouse_name }}</x-table.td>
                            <x-table.td align="right" class="text-slate-600">{{ number_format($row->stock, 3) }}</x-table.td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-8 text-center text-sm text-slate-500">
                                {{ __('products.no_movements') }}
                            </td>
                        </tr>
                    @endforelse
                </x-table>
            @else
                <p class="px-5 py-8 text-center text-sm text-slate-500">—</p>
            @endif
        </x-card>
    </div>

    <x-card title="{{ __('products.movements') }}">
        <x-table>
            @slot('head')
                <tr>
                    <x-table.th>{{ __('products.date') }}</x-table.th>
                    <x-table.th>{{ __('products.warehouse') }}</x-table.th>
                    <x-table.th>{{ __('products.type') }}</x-table.th>
                    <x-table.th align="right">{{ __('products.quantity') }}</x-table.th>
                    <x-table.th align="right">{{ __('products.unit_cost') }}</x-table.th>
                    <x-table.th>{{ __('products.description') }}</x-table.th>
                </tr>
            @endslot
            @forelse ($this->movements as $movement)
                <tr class="transition hover:bg-slate-50" wire:key="movement-{{ $movement->id }}">
                    <x-table.td class="text-slate-600">
                        {{ \Carbon\Carbon::parse($movement->movement_date)->format('Y-m-d') }}
                    </x-table.td>
                    <x-table.td class="text-slate-600">{{ $movement->warehouse?->name ?? '—' }}</x-table.td>
                    <x-table.td>
                        @if ($movement->movement_type === MovementType::IN->value)
                            <x-badge color="emerald">{{ __('products.movement_in') }}</x-badge>
                        @else
                            <x-badge color="amber">{{ __('products.movement_out') }}</x-badge>
                        @endif
                    </x-table.td>
                    <x-table.td align="right" class="text-slate-600">{{ number_format((float) $movement->quantity, 3) }}</x-table.td>
                    <x-table.td align="right" class="text-slate-600">{{ number_format((float) $movement->unit_cost, 2) }}</x-table.td>
                    <x-table.td class="text-slate-500">{{ $movement->note ?? '—' }}</x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">
                        {{ __('products.no_movements') }}
                    </td>
                </tr>
            @endforelse
        </x-table>
        <div class="px-4 py-4">
            {{ $this->movements->links() }}
        </div>
    </x-card>
</div>
