<?php

use App\Enums\MovementType;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\StockMovementSource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    #[Url]
    public string $movementType = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    /**
     * @return array<int|string, string>
     */
    #[Computed]
    public function warehouseOptions(): array
    {
        return ['' => __('stock_movements.all_warehouses')]
            + Warehouse::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function directionOptions(): array
    {
        return [
            '' => __('stock_movements.all_directions'),
            MovementType::IN->value => __('stock_movements.in'),
            MovementType::OUT->value => __('stock_movements.out'),
        ];
    }

    #[Computed]
    public function movements(): LengthAwarePaginator
    {
        $search = $this->search;

        return StockMovement::query()
            ->with(['product', 'warehouse', 'creator'])
            ->when($search !== '', fn ($query) => $query->whereHas('product', fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->when($this->warehouse !== '', fn ($query) => $query->where('warehouse_id', $this->warehouse))
            ->when($this->movementType !== '', fn ($query) => $query->where('movement_type', $this->movementType))
            ->when($this->dateFrom !== '', fn ($query) => $query->whereDate('movement_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($query) => $query->whereDate('movement_date', '<=', $this->dateTo))
            ->latest('movement_date')
            ->latest('id')
            ->paginate(10);
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6">
    <x-page-header :title="__('stock_movements.title')" :subtitle="__('stock_movements.subtitle')">
        <x-slot:actions>
            <x-button :href="route('stock-movements.create')">
                {{ __('stock_movements.new_adjustment') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-form.input name="search" wire:model.live.debounce.300ms="search" :label="__('general.search')"
                :placeholder="__('stock_movements.search_placeholder')" />
            <x-form.select name="warehouse" wire:model.live="warehouse" :label="__('stock_movements.warehouse')"
                :options="$this->warehouseOptions" :selected="$warehouse" />
            <x-form.select name="movementType" wire:model.live="movementType" :label="__('stock_movements.direction')"
                :options="$this->directionOptions" :selected="$movementType" />
            <x-form.input type="date" name="dateFrom" wire:model.live="dateFrom" :label="__('stock_movements.date_from')" />
            <x-form.input type="date" name="dateTo" wire:model.live="dateTo" :label="__('stock_movements.date_to')" />
        </div>
    </x-card>

    <x-card>
        <x-table>
            @slot('head')
                <tr>
                    <x-table.th>{{ __('stock_movements.date') }}</x-table.th>
                    <x-table.th>{{ __('stock_movements.source') }}</x-table.th>
                    <x-table.th>{{ __('stock_movements.product') }}</x-table.th>
                    <x-table.th>{{ __('stock_movements.warehouse') }}</x-table.th>
                    <x-table.th>{{ __('stock_movements.direction') }}</x-table.th>
                    <x-table.th align="right">{{ __('stock_movements.quantity') }}</x-table.th>
                    <x-table.th align="right">{{ __('stock_movements.unit_cost') }}</x-table.th>
                    <x-table.th align="right">{{ __('stock_movements.balance_after') }}</x-table.th>
                    <x-table.th>{{ __('stock_movements.reason_source') }}</x-table.th>
                    <x-table.th>{{ __('stock_movements.created_by') }}</x-table.th>
                    <x-table.th>{{ __('stock_movements.note') }}</x-table.th>
                    <x-table.th>{{ __('stock_movements.override') }}</x-table.th>
                </tr>
            @endslot
            @forelse ($this->movements as $movement)
                @php
                    $source = StockMovementSource::describe($movement);
                @endphp
                <tr class="transition hover:bg-slate-50" wire:key="stock-movement-{{ $movement->id }}">
                    <x-table.td class="whitespace-nowrap text-slate-500">
                        {{ $movement->movement_date }}
                    </x-table.td>
                    <x-table.td class="font-medium text-slate-900">
                        @if ($source['url'] !== null)
                            <a href="{{ $source['url'] }}" wire:navigate
                                class="text-indigo-600 hover:text-indigo-800 hover:underline">
                                {{ $source['label'] }}
                            </a>
                        @else
                            {{ $source['label'] }}
                        @endif
                    </x-table.td>
                    <x-table.td class="text-slate-600">
                        {{ $movement->product?->name ?? '—' }}
                        @if ($movement->product?->code)
                            <span class="text-slate-400">({{ $movement->product->code }})</span>
                        @endif
                    </x-table.td>
                    <x-table.td class="text-slate-600">{{ $movement->warehouse?->name ?? '—' }}</x-table.td>
                    <x-table.td>
                        <x-badge :color="$movement->movement_type === MovementType::IN->value ? 'emerald' : 'amber'">
                            {{ $movement->movement_type === MovementType::IN->value ? __('stock_movements.in') : __('stock_movements.out') }}
                        </x-badge>
                    </x-table.td>
                    <x-table.td align="right" class="text-slate-600">{{ number_format((float) $movement->quantity, 3) }}</x-table.td>
                    <x-table.td align="right" class="text-slate-600">{{ number_format((float) $movement->unit_cost, 2) }}</x-table.td>
                    <x-table.td align="right" class="font-semibold text-slate-900">
                        {{ $movement->balance_after === null ? '—' : number_format((float) $movement->balance_after, 3) }}
                    </x-table.td>
                    <x-table.td class="text-slate-600">{{ StockMovementSource::typeLabel($movement) }}</x-table.td>
                    <x-table.td class="text-slate-600">{{ $movement->creator?->name ?? '—' }}</x-table.td>
                    <x-table.td class="text-slate-500">{{ $movement->note ?? '—' }}</x-table.td>
                    <x-table.td>
                        @if ($movement->is_override)
                            <x-badge color="rose">{{ __('stock_movements.override') }}</x-badge>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="px-4 py-8 text-center text-sm text-slate-500">
                        {{ __('stock_movements.no_movements') }}
                    </td>
                </tr>
            @endforelse
        </x-table>
        <div class="px-4 py-4">
            {{ $this->movements->links() }}
        </div>
    </x-card>
</div>
