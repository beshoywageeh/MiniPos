<?php

use App\Models\Brand;
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
    public function brands()
    {
        return Brand::query()
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%");
                });
            })
            ->latest('id')
            ->paginate(10);
    }

    #[On('brand-created')]
    #[On('brand-updated')]
    #[On('brand-deleted')]
    public function refreshList(): void
    {
        //
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6" x-data="{ flash: false, flashMessage: '' }"
    @brand-created.window="flashMessage = @js(__('brands.created')); flash = true; setTimeout(() => flash = false, 3000)"
    @brand-updated.window="flashMessage = @js(__('brands.updated')); flash = true; setTimeout(() => flash = false, 3000)"
    @brand-deleted.window="flashMessage = @js(__('brands.deleted')); flash = true; setTimeout(() => flash = false, 3000)">
    <div x-show="flash" x-cloak x-transition.opacity
        class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
        <span x-text="flashMessage"></span>
    </div>

    <x-page-header :title="__('brands.title')" :subtitle="__('brands.subtitle')">
        <x-slot:actions>
            <livewire:livewire.catalog.brands.actions.create />
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <x-form.input name="search" wire:model.live.debounce.300ms="search" :label="__('brands.search')" />

        <x-table>
            @slot('head')
                <tr>
                    <x-table.th>#</x-table.th>
                    <x-table.th>{{ __('brands.code') }}</x-table.th>
                    <x-table.th>{{ __('brands.name') }}</x-table.th>
                    <x-table.th>{{ __('general.created_at') }}</x-table.th>
                    <x-table.th>{{ __('general.actions') }}</x-table.th>
                </tr>
            @endslot
            @forelse ($this->brands as $brand)
                <tr class="transition hover:bg-slate-50" wire:key="brand-{{ $brand->id }}">
                    <x-table.td class="text-slate-500">{{ $loop->index + 1 }}</x-table.td>
                    <x-table.td class="text-slate-500">{{ $brand->code }}</x-table.td>
                    <x-table.td class="font-medium text-slate-900">{{ $brand->name }}</x-table.td>
                    <x-table.td class="text-slate-500">{{ $brand->created_at->format('Y-m-d') }}</x-table.td>
                    <x-table.td>
                        <livewire:livewire.catalog.brands.actions.update :brand="$brand"
                            wire:key="brand-update-{{ $brand->id }}" />
                        <livewire:livewire.catalog.brands.actions.delete :brand="$brand"
                            wire:key="brand-delete-{{ $brand->id }}" />
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">
                        {{ __('brands.no_records') }}
                    </td>
                </tr>
            @endforelse
        </x-table>
        <div class="px-4 py-4">
            {{ $this->brands->links() }}
        </div>
    </x-card>
</div>
