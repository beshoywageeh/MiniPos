<?php

use App\Models\Category;
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
    public function categories()
    {
        return Category::query()
            ->with('parent')
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%");
                });
            })
            ->latest('id')
            ->paginate(10);
    }

    #[On('category-created')]
    #[On('category-updated')]
    #[On('category-deleted')]
    public function refreshList(): void
    {
        //
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6" x-data="{ flash: false, flashMessage: '' }"
    @category-created.window="flashMessage = @js(__('categories.created')); flash = true; setTimeout(() => flash = false, 3000)"
    @category-updated.window="flashMessage = @js(__('categories.updated')); flash = true; setTimeout(() => flash = false, 3000)"
    @category-deleted.window="flashMessage = @js(__('categories.deleted')); flash = true; setTimeout(() => flash = false, 3000)">
    <div x-show="flash" x-cloak x-transition.opacity
        class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
        <span x-text="flashMessage"></span>
    </div>

    <x-page-header :title="__('categories.title')" :subtitle="__('categories.subtitle')">
        <x-slot:actions>
            <livewire:livewire.catalog.categories.actions.create />
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <x-form.input name="search" wire:model.live.debounce.300ms="search" :label="__('categories.search')" />

        <x-table>
            @slot('head')
                <tr>
                    <x-table.th>#</x-table.th>
                    <x-table.th>{{ __('categories.code') }}</x-table.th>
                    <x-table.th>{{ __('categories.name') }}</x-table.th>
                    <x-table.th>{{ __('categories.parent') }}</x-table.th>
                    <x-table.th>{{ __('general.created_at') }}</x-table.th>
                    <x-table.th>{{ __('general.actions') }}</x-table.th>
                </tr>
            @endslot
            @forelse ($this->categories as $category)
                <tr class="transition hover:bg-slate-50" wire:key="category-{{ $category->id }}">
                    <x-table.td class="text-slate-500">{{ $loop->index + 1 }}</x-table.td>
                    <x-table.td class="text-slate-500">{{ $category->code }}</x-table.td>
                    <x-table.td class="font-medium text-slate-900">{{ $category->name }}</x-table.td>
                    <x-table.td class="text-slate-600">{{ $category->parent?->name ?? '—' }}</x-table.td>
                    <x-table.td class="text-slate-500">{{ $category->created_at->format('Y-m-d') }}</x-table.td>
                    <x-table.td>
                        <livewire:livewire.catalog.categories.actions.update :category="$category"
                            wire:key="category-update-{{ $category->id }}" />
                        <livewire:livewire.catalog.categories.actions.delete :category="$category"
                            wire:key="category-delete-{{ $category->id }}" />
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">
                        {{ __('categories.no_records') }}
                    </td>
                </tr>
            @endforelse
        </x-table>
        <div class="px-4 py-4">
            {{ $this->categories->links() }}
        </div>
    </x-card>
</div>
