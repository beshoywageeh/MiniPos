<?php

use App\Models\Unit;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component {
    public string $code = '';

    public string $name = '';

    public string $description = '';
    public string $abbreviation = '';

    public bool $isActive = true;

    public function create(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('units', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'abbreviation' => ['nullable', 'string'],
            'isActive' => ['boolean'],
        ]);

        Unit::create([
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'abbreviation' => $this->abbreviation,
            'is_active' => $this->isActive,
        ]);

        $this->reset('code', 'name', 'description', 'isActive');
        $this->dispatch('unit-created');
    }
};
?>

<div>
    <x-modal @unit-created.window="open = false">
        <x-slot:trigger>
            <x-button @click="open = true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('general.add') }}
            </x-button>
        </x-slot:trigger>

        <x-slot:title>
            {{ __('general.add') }}
        </x-slot:title>

        <form class="space-y-4" wire:submit="create">
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
