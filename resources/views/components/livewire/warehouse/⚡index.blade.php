<?php

use App\Models\Warehouse;
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
    public function warehouses()
    {
        return Warehouse::query()
            ->withCount('sales')
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query
                        ->where('code', 'like', "%{$this->search}%")
                        ->orWhere('name', 'like', "%{$this->search}%")
                        ->orWhere('address', 'like', "%{$this->search}%");
                });
            })
            ->latest('id')
            ->paginate(10);
    }

    #[On('warehouse-created')]
    #[On('warehouse-updated')]
    #[On('warehouse-deleted')]
    public function refreshList(): void
    {
        //
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6" x-data="{ flash: false, flashMessage: '' }"
    @warehouse-created.window="flashMessage = @js(__('warehouses.created')); flash = true; setTimeout(() => flash = false, 3000)"
    @warehouse-updated.window="flashMessage = @js(__('warehouses.updated')); flash = true; setTimeout(() => flash = false, 3000)"
    @warehouse-deleted.window="flashMessage = @js(__('warehouses.deleted')); flash = true; setTimeout(() => flash = false, 3000)">
    <div x-show="flash" x-cloak x-transition.opacity
        class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
        <span x-text="flashMessage"></span>
    </div>

    <x-page-header :title="__('warehouses.title')" :subtitle="__('warehouses.subtitle')">
        <x-slot:actions>
            <livewire:livewire.warehouse.actions.create />
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <x-form.input name="search" wire:model.live.debounce.300ms="search" :label="__('general.search')" />
        <x-table>
            @slot('head')
                <tr>
                    <x-table.th>#</x-table.th>
                    <x-table.th>{{ __('warehouses.code') }}</x-table.th>
                    <x-table.th>{{ __('warehouses.name') }}</x-table.th>
                    <x-table.th>{{ __('warehouses.address') }}</x-table.th>
                    <x-table.th>{{ __('warehouses.status') }}</x-table.th>
                    <x-table.th>{{ __('warehouses.sales') }}</x-table.th>
                    <x-table.th>{{ __('general.created_at') }}</x-table.th>
                    <x-table.th>{{ __('general.actions') }}</x-table.th>
                </tr>
            @endslot
            @forelse ($this->warehouses as $warehouse)
                <tr class="transition hover:bg-slate-50" wire:key="warehouse-{{ $warehouse->id }}">
                    <x-table.td class="text-slate-500">{{ $loop->index + 1 }}</x-table.td>
                    <x-table.td class="text-slate-500">{{ $warehouse->code }}</x-table.td>
                    <x-table.td class="font-medium text-slate-900">{{ $warehouse->name }}</x-table.td>
                    <x-table.td class="text-slate-600">{{ $warehouse->address }}</x-table.td>
                    <x-table.td>
                        <x-badge :color="$warehouse->is_active ? 'emerald' : 'slate'">
                            {{ $warehouse->is_active ? __('warehouses.active') : __('warehouses.inactive') }}
                        </x-badge>
                    </x-table.td>
                    <x-table.td>
                        <a href="{{ route('sales.index', ['warehouse' => $warehouse->id]) }}"
                            class="font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
                            {{ $warehouse->sales_count }}
                        </a>
                    </x-table.td>
                    <x-table.td class="text-slate-500">{{ $warehouse->created_at->format('Y-m-d') }}</x-table.td>
                    <x-table.td>
                        <livewire:livewire.warehouse.actions.update :warehouse="$warehouse"
                            wire:key="warehouse-update-{{ $warehouse->id }}" />
                        <livewire:livewire.warehouse.actions.delete :warehouse="$warehouse"
                            wire:key="warehouse-delete-{{ $warehouse->id }}" />
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">
                        {{ __('warehouses.no_records') }}
                    </td>
                </tr>
            @endforelse
        </x-table>
        <div class="px-4 py-4">
            {{ $this->warehouses->links() }}
        </div>
    </x-card>
</div>
