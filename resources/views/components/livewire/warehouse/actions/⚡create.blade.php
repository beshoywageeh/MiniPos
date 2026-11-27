<?php

use App\Models\Warehouse;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component {
    public string $code = '';

    public string $name = '';

    public string $address = '';

    public string $note = '';

    public bool $isActive = true;

    public function create(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('warehouses', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'isActive' => ['boolean'],
        ]);

        Warehouse::create([
            'code' => $this->code,
            'name' => $this->name,
            'address' => $this->address,
            'note' => $this->note,
            'is_active' => $this->isActive,
        ]);

        $this->reset('code', 'name', 'address', 'note', 'isActive');
        $this->resetValidation();
        $this->dispatch('warehouse-created');
    }
};
?>

<div>
    <x-modal @warehouse-created.window="open = false">
        <x-slot:trigger>
            <x-button @click="open = true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('warehouses.add') }}
            </x-button>
        </x-slot:trigger>

        <x-slot:title>
            {{ __('warehouses.add') }}
        </x-slot:title>

        <form class="space-y-4" wire:submit="create">
            <x-form.input name="code" wire:model="code" :label="__('warehouses.code')" />
            <x-form.input name="name" wire:model="name" :label="__('warehouses.name')" />
            <x-form.textarea name="address" wire:model="address" :label="__('warehouses.address')" />
            <x-form.textarea name="note" wire:model="note" :label="__('warehouses.note')" />
            <x-form.checkbox name="isActive" wire:model="isActive" :label="__('warehouses.is_active')" :checked="$isActive" />

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
