<?php

use App\Models\Unit;
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
    public function units()
    {
        return Unit::query()
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query
                        ->where('name', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%")
                        ->orWhere('abbreviation', 'like', "%{$this->search}%");
                });
            })
            ->latest('id')
            ->paginate(10);
    }

    #[On('unit-created')]
    #[On('unit-updated')]
    #[On('unit-deleted')]
    public function refreshList(): void
    {
        //
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6" x-data="{ flash: false, flashMessage: '' }"
    @unit-created.window="flashMessage = @js(__('units.created')); flash = true; setTimeout(() => flash = false, 3000)"
    @unit-updated.window="flashMessage = @js(__('units.updated')); flash = true; setTimeout(() => flash = false, 3000)"
    @unit-deleted.window="flashMessage = @js(__('units.deleted')); flash = true; setTimeout(() => flash = false, 3000)">
    <div x-show="flash" x-cloak x-transition.opacity
        class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
        <span x-text="flashMessage"></span>
    </div>

    <x-page-header :title="__('units.title')" :subtitle="__('units.subtitle')">
        <x-slot:actions>
            <livewire:livewire.unit.actions.create />
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <x-form.input name="search" wire:model.live.debounce.300ms="search" :label="__('general.search')" />
        <x-table>
            @slot('head')
                <tr>
                    <x-table.th>#</x-table.th>
                    <x-table.th>{{ __('units.code') }}</x-table.th>
                    <x-table.th>{{ __('units.name') }}</x-table.th>
                    <x-table.th>{{ __('units.abbreviation') }}</x-table.th>
                    <x-table.th>{{ __('general.created_at') }}</x-table.th>
                    <x-table.th>{{ __('general.actions') }}</x-table.th>
                </tr>
            @endslot
            @forelse ($this->units as $unit)
                <tr class="transition hover:bg-slate-50" wire:key="unit-{{ $unit->id }}">
                    <x-table.td class="text-slate-500">{{ $loop->index + 1 }}</x-table.td>
                    <x-table.td class="text-slate-500">{{ $unit->code }}</x-table.td>
                    <x-table.td class="font-medium text-slate-900">{{ $unit->name }}</x-table.td>
                    <x-table.td class="font-medium text-slate-900">{{ $unit->abbreviation }}</x-table.td>
                    <x-table.td class="text-slate-500">{{ $unit->created_at->format('Y-m-d') }}</x-table.td>
                    <x-table.td>
                        <livewire:livewire.unit.actions.update :unit="$unit"
                            wire:key="unit-update-{{ $unit->id }}" />
                        <livewire:livewire.unit.actions.delete :unit="$unit"
                            wire:key="unit-delete-{{ $unit->id }}" />
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">
                        {{ __('units.no_records') }}
                    </td>
                </tr>
            @endforelse
        </x-table>
        <div class="px-4 py-4">
            {{ $this->units->links() }}
        </div>
    </x-card>
</div>
