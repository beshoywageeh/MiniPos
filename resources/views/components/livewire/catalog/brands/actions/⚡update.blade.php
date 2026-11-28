<?php

use App\Models\Brand;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component {
    public Brand $brand;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public bool $isActive = true;

    public function mount(Brand $brand): void
    {
        $this->brand = $brand;
        $this->code = $brand->code;
        $this->name = $brand->name;
        $this->description = $brand->description ?? '';
        $this->isActive = $brand->is_active;
    }

    public function update(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('brands', 'code')->ignore($this->brand->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'isActive' => ['boolean'],
        ]);

        $this->brand->update([
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->isActive,
        ]);

        $this->dispatch('brand-updated');
    }
};
?>

<div>
    <x-modal @brand-updated.window="open = false">
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
            <x-form.input name="code" wire:model="code" :label="__('brands.code')" />
            <x-form.input name="name" wire:model="name" :label="__('brands.name')" />
            <x-form.textarea name="description" wire:model="description" :label="__('brands.description')" />
            <x-form.checkbox name="isActive" wire:model="isActive" :label="__('brands.active')" :checked="$isActive" />

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
