<?php

use App\Models\Purchase;
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
        return ['' => __('purchases.all_warehouses')]
            + Warehouse::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    #[Computed]
    public function purchases()
    {
        return Purchase::query()
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
    <x-page-header :title="__('purchases.list_title')" :subtitle="__('purchases.list_subtitle')">
        <x-slot:actions>
            <x-button :href="route('purchases.create')">
                {{ __('purchases.new_purchase') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input name="search" wire:model.live.debounce.300ms="search" :label="__('general.search')" />
            <x-form.select name="warehouse" wire:model.live="warehouse" :label="__('purchases.warehouse')"
                :options="$this->warehouseOptions" :selected="$warehouse" />
        </div>

        @php
            $statusColors = [
                'paid' => 'emerald',
                'partial' => 'amber',
                'pending' => 'sky',
                'draft' => 'slate',
            ];
        @endphp

        <x-table>
            @slot('head')
                <tr>
                    <x-table.th>{{ __('purchases.invoice_number') }}</x-table.th>
                    <x-table.th>{{ __('purchases.date') }}</x-table.th>
                    <x-table.th>{{ __('purchases.supplier') }}</x-table.th>
                    <x-table.th>{{ __('purchases.warehouse') }}</x-table.th>
                    <x-table.th align="right">{{ __('purchases.total') }}</x-table.th>
                    <x-table.th align="right">{{ __('purchases.paid') }}</x-table.th>
                    <x-table.th align="right">{{ __('purchases.remaining') }}</x-table.th>
                    <x-table.th>{{ __('purchases.status') }}</x-table.th>
                    <x-table.th>{{ __('purchases.actions') }}</x-table.th>
                </tr>
            @endslot
            @forelse ($this->purchases as $purchase)
                <tr class="transition hover:bg-slate-50" wire:key="purchase-{{ $purchase->id }}">
                    <x-table.td class="font-medium text-slate-900">
                        <a href="{{ route('purchases.invoice_preview', $purchase) }}" class="text-indigo-600 hover:text-indigo-800 hover:underline">
                            {{ $purchase->document_number }}
                        </a>
                    </x-table.td>
                    <x-table.td class="text-slate-500">{{ $purchase->invoice_date->format('Y-m-d') }}</x-table.td>
                    <x-table.td class="text-slate-600">{{ $purchase->party?->name ?? '—' }}</x-table.td>
                    <x-table.td class="text-slate-600">{{ $purchase->warehouse?->name }}</x-table.td>
                    <x-table.td align="right" class="font-semibold text-slate-900">{{ number_format($purchase->total_amount, 2) }}</x-table.td>
                    <x-table.td align="right" class="text-emerald-600">{{ number_format($purchase->paid_amount, 2) }}</x-table.td>
                    <x-table.td align="right" class="text-rose-600">{{ number_format($purchase->remaining_amount, 2) }}</x-table.td>
                    <x-table.td>
                        <x-badge :color="$statusColors[$purchase->status->value] ?? 'slate'">
                            {{ $purchase->status->label() }}
                        </x-badge>
                    </x-table.td>
                    <x-table.td>
                        <x-button variant="ghost" :href="route('purchases.invoice_preview', $purchase)">
                            {{ __('purchases.view') }}
                        </x-button>
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-sm text-slate-500">
                        {{ __('purchases.no_records') }}
                    </td>
                </tr>
            @endforelse
        </x-table>
        <div class="px-4 py-4">
            {{ $this->purchases->links() }}
        </div>
    </x-card>
</div>
