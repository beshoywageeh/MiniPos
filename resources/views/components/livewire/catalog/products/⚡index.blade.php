<?php

use App\Enums\MovementType;
use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Computed]
    public function products()
    {
        return Product::query()
            ->with(['category', 'brand', 'unit'])
            ->withSum(['stockMovements as stock_in' => fn ($query) => $query->where('movement_type', MovementType::IN)], 'quantity')
            ->withSum(['stockMovements as stock_out' => fn ($query) => $query->where('movement_type', MovementType::OUT)], 'quantity')
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%")
                        ->orWhere('sku', 'like', "%{$this->search}%")
                        ->orWhere('barcode', 'like', "%{$this->search}%");
                });
            })
            ->latest('id')
            ->paginate(10);
    }

    #[On('product-created')]
    #[On('product-updated')]
    #[On('product-deleted')]
    #[On('product-adjusted')]
    public function refreshList(): void
    {
        //
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6" x-data="{ flash: false, flashMessage: '' }"
    @product-created.window="flashMessage = @js(__('products.created')); flash = true; setTimeout(() => flash = false, 3000)"
    @product-updated.window="flashMessage = @js(__('products.updated')); flash = true; setTimeout(() => flash = false, 3000)"
    @product-deleted.window="flashMessage = @js(__('products.deleted')); flash = true; setTimeout(() => flash = false, 3000)"
    @product-adjusted.window="flashMessage = @js(__('products.adjusted')); flash = true; setTimeout(() => flash = false, 3000)">
    <div x-show="flash" x-cloak x-transition.opacity
        class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
        <span x-text="flashMessage"></span>
    </div>

    <x-page-header :title="__('products.title')" :subtitle="__('products.subtitle')">
        <x-slot:actions>
            <livewire:livewire.catalog.products.actions.create />
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <x-form.input name="search" wire:model.live.debounce.300ms="search" :label="__('general.search')" />
        <x-table>
            @slot('head')
                <tr>
                    <x-table.th>#</x-table.th>
                    <x-table.th>{{ __('products.code') }}</x-table.th>
                    <x-table.th>{{ __('products.name') }}</x-table.th>
                    <x-table.th>{{ __('products.category') }}</x-table.th>
                    <x-table.th>{{ __('products.brand') }}</x-table.th>
                    <x-table.th>{{ __('products.unit') }}</x-table.th>
                    <x-table.th>{{ __('products.type') }}</x-table.th>
                    <x-table.th>{{ __('products.selling_price') }}</x-table.th>
                    <x-table.th>{{ __('products.stock') }}</x-table.th>
                    <x-table.th>{{ __('products.active') }}</x-table.th>
                    <x-table.th>{{ __('general.actions') }}</x-table.th>
                </tr>
            @endslot
            @forelse ($this->products as $product)
                <tr class="transition hover:bg-slate-50" wire:key="product-{{ $product->id }}">
                    <x-table.td class="text-slate-500">{{ $loop->index + 1 }}</x-table.td>
                    <x-table.td class="text-slate-500">{{ $product->code }}</x-table.td>
                    <x-table.td class="font-medium text-slate-900">
                        <a href="{{ route('products.show', $product) }}" class="hover:text-indigo-600">{{ $product->name }}</a>
                    </x-table.td>
                    <x-table.td class="text-slate-600">{{ $product->category?->name ?? '—' }}</x-table.td>
                    <x-table.td class="text-slate-600">{{ $product->brand?->name ?? '—' }}</x-table.td>
                    <x-table.td class="text-slate-600">{{ $product->unit?->name ?? '—' }}</x-table.td>
                    <x-table.td class="text-slate-600">{{ $product->type->label() }}</x-table.td>
                    <x-table.td class="text-slate-600">{{ number_format((float) $product->selling_price, 2) }}</x-table.td>
                    <x-table.td class="text-slate-600">
                        @php
                            $stock = (float) ($product->stock_in ?? 0) - (float) ($product->stock_out ?? 0);
                        @endphp

                        @if ($product->type === \App\Enums\ProductType::Product)
                            {{ $stock }}
                            @if ($stock <= (float) $product->min_stock)
                                <x-badge color="rose">{{ __('products.low_stock') }}</x-badge>
                            @endif
                        @else
                            —
                        @endif
                    </x-table.td>
                    <x-table.td>
                        <x-badge :color="$product->is_active ? 'emerald' : 'slate'">
                            {{ $product->is_active ? __('products.active') : __('products.inactive') }}
                        </x-badge>
                    </x-table.td>
                    <x-table.td>
                        <livewire:livewire.catalog.products.actions.adjust :product="$product"
                            wire:key="product-adjust-{{ $product->id }}" />
                        <livewire:livewire.catalog.products.actions.update :product="$product"
                            wire:key="product-update-{{ $product->id }}" />
                        <livewire:livewire.catalog.products.actions.delete :product="$product"
                            wire:key="product-delete-{{ $product->id }}" />
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="px-4 py-8 text-center text-sm text-slate-500">
                        {{ __('products.no_records') }}
                    </td>
                </tr>
            @endforelse
        </x-table>
        <div class="px-4 py-4">
            {{ $this->products->links() }}
        </div>
    </x-card>
</div>
