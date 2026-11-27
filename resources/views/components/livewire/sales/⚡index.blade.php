<?php

use App\Models\Sale;
use App\Models\Warehouse;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $warehouse = '';

    /**
     * @return array<int|string, string>
     */
    #[Computed]
    public function warehouseOptions(): array
    {
        return ['' => __('sales.all_warehouses')]
            + Warehouse::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    #[Computed]
    public function sales()
    {
        return Sale::query()
            ->with(['party', 'warehouse'])
            ->when($this->search !== '', function ($query) {
                $search = $this->search;

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('document_number', 'like', "%{$search}%")
                        ->orWhereHas('party', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($this->warehouse !== '', fn ($query) => $query->where('warehouse_id', $this->warehouse))
            ->latest('invoice_date')
            ->latest('id')
            ->paginate(10);
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6">
    <x-page-header :title="__('sales.list_title')" :subtitle="__('sales.list_subtitle')">
        <x-slot:actions>
            <x-button :href="route('sales.create')">
                {{ __('sales.new_sale') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input name="search" wire:model.live.debounce.300ms="search" :label="__('general.search')" />
            <x-form.select name="warehouse" wire:model.live="warehouse" :label="__('sales.warehouse')"
                :options="$this->warehouseOptions" :selected="$warehouse" />
        </div>

        @php
            $statusColors = [
                'paid' => 'emerald',
                'partial' => 'amber',
                'pending' => 'sky',
                'approved' => 'indigo',
                'draft' => 'slate',
            ];
        @endphp

        <x-table>
            @slot('head')
                <tr>
                    <x-table.th>{{ __('sales.invoice_number') }}</x-table.th>
                    <x-table.th>{{ __('sales.date') }}</x-table.th>
                    <x-table.th>{{ __('sales.customer') }}</x-table.th>
                    <x-table.th>{{ __('sales.warehouse') }}</x-table.th>
                    <x-table.th align="right">{{ __('sales.grand_total') }}</x-table.th>
                    <x-table.th>{{ __('sales.status') }}</x-table.th>
                    <x-table.th>{{ __('sales.actions') }}</x-table.th>
                </tr>
            @endslot
            @forelse ($this->sales as $sale)
                <tr class="transition hover:bg-slate-50" wire:key="sale-{{ $sale->id }}">
                    <x-table.td class="font-medium text-slate-900">
                        <a href="{{ route('sales.invoice_preview', $sale) }}" class="text-indigo-600 hover:text-indigo-800 hover:underline">
                            {{ $sale->document_number }}
                        </a>
                    </x-table.td>
                    <x-table.td class="text-slate-500">{{ $sale->invoice_date->format('Y-m-d') }}</x-table.td>
                    <x-table.td class="text-slate-600">{{ $sale->party?->name ?? __('sales.no_customer') }}</x-table.td>
                    <x-table.td class="text-slate-600">{{ $sale->warehouse?->name }}</x-table.td>
                    <x-table.td align="right" class="font-semibold text-slate-900">{{ number_format($sale->total_amount, 2) }}</x-table.td>
                    <x-table.td>
                        <x-badge :color="$statusColors[$sale->status->value] ?? 'slate'">
                            {{ $sale->status->label() }}
                        </x-badge>
                    </x-table.td>
                    <x-table.td>
                        <x-button variant="ghost" :href="route('sales.invoice_preview', $sale)">
                            {{ __('sales.view') }}
                        </x-button>
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">
                        {{ __('sales.no_records') }}
                    </td>
                </tr>
            @endforelse
        </x-table>
        <div class="px-4 py-4">
            {{ $this->sales->links() }}
        </div>
    </x-card>
</div>
