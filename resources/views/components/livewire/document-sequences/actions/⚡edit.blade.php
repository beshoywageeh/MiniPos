<?php

use App\Enums\ResetPeriod;
use App\Models\DocumentSequence;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public DocumentSequence $sequence;

    public string $prefix = '';

    public string $nextNumber = '';

    public string $padding = '';

    public string $resetPeriod = '';

    public function mount(DocumentSequence $sequence): void
    {
        $this->sequence = $sequence;
        $this->prefix = $sequence->prefix;
        $this->nextNumber = (string) $sequence->next_number;
        $this->padding = (string) $sequence->padding;
        $this->resetPeriod = $sequence->reset_period->value;
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function resetPeriodOptions(): array
    {
        return collect(ResetPeriod::cases())
            ->mapWithKeys(fn (ResetPeriod $period): array => [$period->value => $period->label()])
            ->all();
    }

    public function update(): void
    {
        $this->validate([
            'prefix' => ['required', 'string', 'max:20'],
            'nextNumber' => ['required', 'integer', 'min:1'],
            'padding' => ['required', 'integer', 'min:1', 'max:10'],
            'resetPeriod' => ['required', Rule::in(array_column(ResetPeriod::cases(), 'value'))],
        ]);

        $this->sequence->update([
            'prefix' => $this->prefix,
            'next_number' => $this->nextNumber,
            'padding' => $this->padding,
            'reset_period' => $this->resetPeriod,
        ]);

        $this->dispatch('document-sequence-updated');
    }
};
?>

<div>
    <x-modal maxWidth="md" @document-sequence-updated.window="open = false">
        <x-slot:trigger>
            <x-button variant="ghost" @click="open = true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                </svg>
                {{ __('general.edit') }}
            </x-button>
        </x-slot:trigger>

        <x-slot:title>
            {{ __('document_sequences.edit') }}
        </x-slot:title>

        <form class="space-y-4" wire:submit="update">
            <x-form.input name="prefix" wire:model="prefix" :label="__('document_sequences.prefix')" />
            <x-form.input name="nextNumber" type="number" min="1" wire:model="nextNumber"
                :label="__('document_sequences.next_number')" :help="__('document_sequences.duplicate_warning')" />
            <x-form.input name="padding" type="number" min="1" max="10" wire:model="padding"
                :label="__('document_sequences.padding')" />
            <x-form.select name="resetPeriod" wire:model="resetPeriod" :label="__('document_sequences.reset_period')"
                :options="$this->resetPeriodOptions" :selected="$resetPeriod" />

            <div class="flex justify-end gap-3 pt-2">
                <x-button variant="secondary" @click="open = false">
                    {{ __('general.cancel') }}
                </x-button>
                <x-button type="submit" wire:loading.attr="disabled">
                    {{ __('general.save') }}
                </x-button>
            </div>
        </form>
    </x-modal>
</div>
