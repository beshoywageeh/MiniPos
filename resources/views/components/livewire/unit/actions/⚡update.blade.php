<?php

use App\Models\Unit;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component {
    public Unit $unit;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public bool $isActive = true;

    public function mount(unit $unit): void
    {
        $this->unit = $unit;
        $this->code = $unit->code;
        $this->name = $unit->name;
        $this->description = $unit->description ?? '';
        $this->isActive = $unit->is_active;
    }

    public function update(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('units', 'code')->ignore($this->unit->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'abbreviation' => ['nullable', 'string'],

            'isActive' => ['boolean'],
        ]);

        $this->unit->update([
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'abbreviation' => $this->abbreviation,

            'is_active' => $this->isActive,
        ]);

        $this->dispatch('unit-updated');
    }
};
?>

<div>
    <x-modal @unit-updated.window="open = false">
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
            {{ __('general.edit') }}
        </x-slot:title>

        <form class="space-y-4" wire:submit="update">
            <x-form.input name="code" wire:model="code" :label="__('units.code')" />
            <x-form.input name="name" wire:model="name" :label="__('units.name')" />
            <x-form.input name="abbreviation" wire:model="abbreviation" :label="__('units.abbreviation')" />
            <x-form.textarea name="description" wire:model="description" :label="__('units.description')" />
            <x-form.checkbox name="isActive" wire:model="isActive" :label="__('units.active')" :checked="$isActive" />

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
