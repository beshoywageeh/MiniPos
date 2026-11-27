<?php

use App\Models\Party;
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
    public function parties()
    {
        return Party::query()
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%");
                });
            })
            ->latest('id')
            ->paginate(10);
    }

    #[On('party-created')]
    #[On('party-updated')]
    #[On('party-deleted')]
    public function refreshList(): void
    {
        //
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6" x-data="{ flash: false, flashMessage: '' }"
    @party-created.window="flashMessage = @js(__('parties.created')); flash = true; setTimeout(() => flash = false, 3000)"
    @party-updated.window="flashMessage = @js(__('parties.updated')); flash = true; setTimeout(() => flash = false, 3000)"
    @party-deleted.window="flashMessage = @js(__('parties.deleted')); flash = true; setTimeout(() => flash = false, 3000)">
    <div x-show="flash" x-cloak x-transition.opacity
        class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
        <span x-text="flashMessage"></span>
    </div>

    <x-page-header :title="__('parties.title')" :subtitle="__('parties.subtitle')">
        <x-slot:actions>
            <livewire:livewire.party.actions.create />
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <x-form.input name="search" wire:model.live.debounce.300ms="search" :label="__('general.search')" />

        <x-table>
            @slot('head')
                <tr>
                    <x-table.th>#</x-table.th>
                    <x-table.th>{{ __('parties.code') }}</x-table.th>
                    <x-table.th>{{ __('parties.name') }}</x-table.th>
                    <x-table.th>{{ __('parties.phone') }}</x-table.th>
                    <x-table.th>{{ __('parties.address') }}</x-table.th>
                    <x-table.th>{{ __('parties.type') }}</x-table.th>
                    <x-table.th>{{ __('general.created_at') }}</x-table.th>
                    <x-table.th>{{ __('general.actions') }}</x-table.th>
                </tr>
            @endslot
            @forelse ($this->parties as $party)
                <tr class="transition hover:bg-slate-50" wire:key="party-{{ $party->id }}">
                    <x-table.td class="text-slate-500">{{ $loop->index + 1 }}</x-table.td>
                    <x-table.td class="text-slate-500">{{ $party->code }}</x-table.td>
                    <x-table.td class="font-medium text-slate-900">
                        <a href="{{ route('parties.show', $party) }}" class="hover:text-indigo-600">{{ $party->name }}</a>
                    </x-table.td>
                    <x-table.td class="text-slate-600">{{ $party->phone }}</x-table.td>
                    <x-table.td class="text-slate-600">{{ $party->address ?: '—' }}</x-table.td>
                    <x-table.td class="text-slate-600">{{ $party->type->label() }}</x-table.td>
                    <x-table.td class="text-slate-500">{{ $party->created_at->format('Y-m-d') }}</x-table.td>
                    <x-table.td>
                        <livewire:livewire.party.actions.update :party="$party"
                            wire:key="party-update-{{ $party->id }}" />
                        <livewire:livewire.party.actions.delete :party="$party"
                            wire:key="party-delete-{{ $party->id }}" />
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">
                        {{ __('parties.no_records') }}
                    </td>
                </tr>
            @endforelse
        </x-table>
        <div class="px-4 py-4">
            {{ $this->parties->links() }}
        </div>
    </x-card>
</div>
