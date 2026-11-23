<?php

use App\Models\DocumentSequence;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function sequences()
    {
        return DocumentSequence::query()->orderBy('document_type')->get();
    }

    #[On('document-sequence-updated')]
    public function refreshList(): void
    {
        //
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6" x-data="{ flash: false, flashMessage: '' }"
    @document-sequence-updated.window="flashMessage = @js(__('document_sequences.updated')); flash = true; setTimeout(() => flash = false, 3000)">
    <div x-show="flash" x-cloak x-transition.opacity
        class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
        <span x-text="flashMessage"></span>
    </div>

    <x-page-header :title="__('document_sequences.title')" :subtitle="__('document_sequences.subtitle')" />

    <x-card>
        <x-table>
            @slot('head')
                <tr>
                    <x-table.th>{{ __('document_sequences.type') }}</x-table.th>
                    <x-table.th>{{ __('document_sequences.prefix') }}</x-table.th>
                    <x-table.th>{{ __('document_sequences.next_number') }}</x-table.th>
                    <x-table.th>{{ __('document_sequences.preview') }}</x-table.th>
                    <x-table.th>{{ __('document_sequences.padding') }}</x-table.th>
                    <x-table.th>{{ __('document_sequences.reset_period') }}</x-table.th>
                    <x-table.th>{{ __('document_sequences.last_reset') }}</x-table.th>
                    <x-table.th>{{ __('general.actions') }}</x-table.th>
                </tr>
            @endslot
            @forelse ($this->sequences as $sequence)
                <tr class="transition hover:bg-slate-50" wire:key="document-sequence-{{ $sequence->id }}">
                    <x-table.td class="font-medium text-slate-900">{{ str($sequence->document_type)->headline() }}</x-table.td>
                    <x-table.td class="text-slate-600">{{ $sequence->prefix }}</x-table.td>
                    <x-table.td class="text-slate-600">{{ $sequence->next_number }}</x-table.td>
                    <x-table.td class="font-mono text-slate-900">{{ \App\Support\DocumentNumber::preview($sequence) }}</x-table.td>
                    <x-table.td class="text-slate-600">{{ $sequence->padding }}</x-table.td>
                    <x-table.td class="text-slate-600">{{ $sequence->reset_period->label() }}</x-table.td>
                    <x-table.td class="text-slate-500">{{ $sequence->last_reset_at?->format('Y-m-d H:i') ?? '—' }}</x-table.td>
                    <x-table.td>
                        <livewire:livewire.document-sequences.actions.edit :sequence="$sequence"
                            wire:key="document-sequence-edit-{{ $sequence->id }}" />
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <x-table.td colspan="8" class="py-6 text-center text-slate-500">—</x-table.td>
                </tr>
            @endforelse
        </x-table>
    </x-card>
</div>
